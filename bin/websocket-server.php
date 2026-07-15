<?php

declare(strict_types=1);

/**
 * Stand-alone WebSocket daemon: `php bin/websocket-server.php`.
 *
 * A separate long-running process from Apache/PHP-FPM (which can't hold
 * bidirectional connections open across requests) - it never touches the
 * database. Two listeners multiplexed in one stream_select() loop:
 *
 *   - the public WebSocket port browsers connect to (WSPort), authenticated
 *     via a short-lived WSToken passed as a query string parameter (the
 *     standard approach for browser WebSocket auth, since the browser
 *     WebSocket API can't set custom headers on the handshake)
 *   - a loopback-only "push" port (WSPushPort) that api/*.php scripts
 *     (running in a normal Apache/PHP-FPM request) connect to briefly to
 *     say "deliver this JSON payload to this userId's open connection(s)"
 *
 * Every frame the server sends is a single unfragmented text frame; every
 * frame it accepts from a client may be fragmented (per spec, client frames
 * are always masked - server frames never are).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

function log_line(string $message): void
{
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n");
}

/**
 * Sends a systemd service notification (e.g. WATCHDOG=1) to $NOTIFY_SOCKET.
 * A no-op when not run under systemd (the socket env var is unset), so running
 * the daemon by hand still works. Best-effort - any failure is swallowed rather
 * than risk taking the daemon down over a status notification.
 */
function sd_notify(string $state): void
{
    $socket_path = getenv('NOTIFY_SOCKET');

    if ($socket_path === false || $socket_path === '') {
        return;
    }

    // An '@'-prefixed path names an abstract-namespace socket, where the '@'
    // stands in for a leading NUL byte.
    if ($socket_path[0] === '@') {
        $socket_path = "\0" . substr($socket_path, 1);
    }

    $socket = @socket_create(AF_UNIX, SOCK_DGRAM, 0);

    if ($socket === false) {
        return;
    }

    @socket_sendto($socket, $state, strlen($state), 0, $socket_path);
    socket_close($socket);
}

$ws_tls_cert = Config::get('WSTLSCert');
$ws_tls_key = Config::get('WSTLSKey');
$ws_host = Config::get('WSHost');
$ws_port = Config::get('WSPort');
$ws_push_port = Config::get('WSPushPort');
$ws_secret = Config::get('WSSecret');

$use_tls = $ws_tls_cert !== null && $ws_tls_key !== null;

$context = stream_context_create();

if ($use_tls) {
    stream_context_set_option($context, 'ssl', 'local_cert', $ws_tls_cert);
    stream_context_set_option($context, 'ssl', 'local_pk', $ws_tls_key);
    stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
    stream_context_set_option($context, 'ssl', 'verify_peer', false);
}

$public_listener = stream_socket_server(
    'tcp://' . $ws_host . ':' . $ws_port,
    $error_code,
    $error_message,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);

if ($public_listener === false) {
    log_line('Could not bind public WebSocket listener on ' . $ws_host . ':' . $ws_port . ' - ' . $error_message);
    exit(1);
}

// Loopback only - this port is never meant to be reachable from outside the
// machine, api/*.php scripts are its only legitimate callers.
$push_listener = stream_socket_server(
    'tcp://127.0.0.1:' . $ws_push_port,
    $error_code,
    $error_message,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);

if ($push_listener === false) {
    log_line('Could not bind internal push listener on 127.0.0.1:' . $ws_push_port . ' - ' . $error_message);
    exit(1);
}

stream_set_blocking($public_listener, false);
stream_set_blocking($push_listener, false);

log_line('Listening: public ws' . ($use_tls ? 's' : '') . '://' . $ws_host . ':' . $ws_port . ', internal push 127.0.0.1:' . $ws_push_port);

// systemd watchdog. WATCHDOG_USEC is set only when the unit configures
// WatchdogSec; ping at half that interval (systemd's recommendation) so a hung
// event loop - one that stops reaching stream_select() - is noticed and the
// service restarted. Disabled (interval 0) when run by hand or without a
// watchdog configured. An immediate first ping arms it right after startup.
$watchdog_usec = (int) (getenv('WATCHDOG_USEC') ?: 0);
$watchdog_interval = $watchdog_usec > 0 ? $watchdog_usec / 2000000 : 0.0;
$last_watchdog_ping = microtime(true);

if ($watchdog_interval > 0) {
    sd_notify('WATCHDOG=1');
}

/**
 * One entry per connected socket, keyed by (int) $socket (PHP resource IDs
 * are unique for the process lifetime among currently-open resources).
 *
 * @var array<int, array{
 *     socket: resource,
 *     kind: 'client'|'push',
 *     handshakeDone: bool,
 *     tlsReady: bool,
 *     recvBuffer: string,
 *     sendBuffer: string,
 *     userId: ?int,
 *     fragOpcode: ?int,
 *     fragBuffer: string,
 *     connectedAt: float,
 *     lastActivityAt: float,
 *     pingSentAt: ?float,
 * }>
 */
$connections = [];

/** @var array<int, int[]> userId => list of connection ids (a user can have several tabs open) */
$connectionsByUser = [];

// Hard per-connection buffer caps - without these, a client could declare a
// multi-gigabyte frame length (or stream bytes without ever completing a
// handshake or frame, or send endless unfinished continuation frames) and
// the daemon would buffer it all until the process OOMs, dropping every
// live connection at once. Nothing legitimate comes close to these sizes:
// browser clients only ever send the handshake and control frames, and push
// payloads are single notification/message JSON lines.
const MAX_CLIENT_BUFFER_BYTES = 65536;
const MAX_PUSH_BUFFER_BYTES = 262144;
const MAX_SEND_BUFFER_BYTES = 1048576;

// A client that never finishes its WebSocket handshake (or a TCP connection
// that never sends anything at all) would otherwise hold its fd and
// connection slot forever - a trivial DoS via a modest number of held-open
// sockets. A real browser completes the handshake almost immediately.
const HANDSHAKE_TIMEOUT_SECONDS = 10;

// This channel is push-only (server -> client) once handshaked, so a
// legitimate client can sit silent indefinitely - "idle" alone can't mean
// "dead". These instead detect a genuinely dead peer (frozen tab, dropped NAT
// mapping) the same way any long-lived TCP connection has to: ping it, and
// drop it if nothing - not even the pong - comes back in time.
const CLIENT_PING_INTERVAL_SECONDS = 30;
const CLIENT_PONG_GRACE_SECONDS = 60;

// A push caller (api/*.php scripts, always on this same machine) sends its
// one complete line almost instantly and gets closeAfterFlush set right
// after handle_push_request() processes it - so anything still open this
// long after connecting never sent a complete line (or never really
// intended to), and would otherwise leak its fd/connection slot forever the
// same way an unfinished client handshake would.
const PUSH_CONNECTION_TIMEOUT_SECONDS = 10;

function register_connection($socket, string $kind): int
{
    global $connections;

    stream_set_blocking($socket, false);
    $id = (int) $socket;

    $now = microtime(true);

    $connections[$id] = [
        'socket' => $socket,
        'kind' => $kind,
        'handshakeDone' => false,
        'tlsReady' => $kind !== 'client' || !ws_uses_tls(),
        'recvBuffer' => '',
        'sendBuffer' => '',
        'userId' => null,
        'fragOpcode' => null,
        'fragBuffer' => '',
        'connectedAt' => $now,
        'lastActivityAt' => $now,
        'pingSentAt' => null,
    ];

    return $id;
}

/**
 * Drops any client connection stuck without a completed handshake past
 * HANDSHAKE_TIMEOUT_SECONDS, pings any handshake-complete client that's gone
 * quiet for CLIENT_PING_INTERVAL_SECONDS, and drops one that still hasn't
 * made a peep (pong or otherwise) CLIENT_PONG_GRACE_SECONDS after that ping.
 * Also drops a push connection that's sat open past
 * PUSH_CONNECTION_TIMEOUT_SECONDS without ever completing its line (a
 * well-behaved push caller never gets anywhere near this - it's already
 * gone via closeAfterFlush) - push connections are short-lived by
 * construction, but only once handle_push_request() actually got a complete
 * line to process, so a stalled one needs the same kind of timeout a client
 * stuck mid-handshake does.
 */
function reap_stale_connections(): void
{
    global $connections;

    $now = microtime(true);

    foreach ($connections as $id => $connection) {
        if ($connection['kind'] === 'push') {
            if ($now - $connection['connectedAt'] > PUSH_CONNECTION_TIMEOUT_SECONDS) {
                drop_connection($id);
            }

            continue;
        }

        if (!$connection['handshakeDone']) {
            if ($now - $connection['connectedAt'] > HANDSHAKE_TIMEOUT_SECONDS) {
                drop_connection($id);
            }

            continue;
        }

        if ($connection['pingSentAt'] !== null) {
            if ($now - $connection['pingSentAt'] > CLIENT_PONG_GRACE_SECONDS) {
                drop_connection($id);
            }

            continue;
        }

        if ($now - $connection['lastActivityAt'] > CLIENT_PING_INTERVAL_SECONDS) {
            $connections[$id]['sendBuffer'] .= ws_encode_ping_frame();
            $connections[$id]['pingSentAt'] = $now;
        }
    }
}

function ws_uses_tls(): bool
{
    global $use_tls;

    return $use_tls;
}

function drop_connection(int $id): void
{
    global $connections, $connectionsByUser;

    if (!isset($connections[$id])) {
        return;
    }

    $user_id = $connections[$id]['userId'];

    if ($user_id !== null && isset($connectionsByUser[$user_id])) {
        $connectionsByUser[$user_id] = array_values(array_filter(
            $connectionsByUser[$user_id],
            fn (int $existing_id) => $existing_id !== $id
        ));

        if ($connectionsByUser[$user_id] === []) {
            unset($connectionsByUser[$user_id]);
        }
    }

    fclose($connections[$id]['socket']);
    unset($connections[$id]);
}

function attach_user(int $id, int $user_id): void
{
    global $connections, $connectionsByUser;

    $connections[$id]['userId'] = $user_id;
    $connectionsByUser[$user_id][] = $id;
}

// ---------- WebSocket framing ----------

/**
 * Encodes a single unfragmented, unmasked text frame (server -> client
 * frames are never masked, per RFC 6455).
 */
function ws_encode_text_frame(string $payload): string
{
    $length = strlen($payload);
    $header = chr(0x80 | 0x1); // FIN=1, opcode=1 (text)

    if ($length <= 125) {
        $header .= chr($length);
    } elseif ($length <= 0xFFFF) {
        $header .= chr(126) . pack('n', $length);
    } else {
        $header .= chr(127) . pack('J', $length);
    }

    return $header . $payload;
}

function ws_encode_close_frame(int $code = 1000): string
{
    $payload = pack('n', $code);

    return chr(0x80 | 0x8) . chr(strlen($payload)) . $payload;
}

function ws_encode_pong_frame(string $payload = ''): string
{
    return chr(0x80 | 0xA) . chr(strlen($payload)) . $payload;
}

function ws_encode_ping_frame(string $payload = ''): string
{
    return chr(0x80 | 0x9) . chr(strlen($payload)) . $payload;
}

/**
 * Parses as many complete frames as are available in $buffer, returning
 * them and leaving any trailing partial frame in $buffer for next time.
 *
 * @return array{frames: array{fin: bool, opcode: int, payload: string}[], buffer: string}
 */
function ws_decode_frames(string $buffer): array
{
    $frames = [];

    while (true) {
        if (strlen($buffer) < 2) {
            break;
        }

        $first_byte = ord($buffer[0]);
        $second_byte = ord($buffer[1]);

        $fin = ($first_byte & 0x80) !== 0;
        $opcode = $first_byte & 0x0F;
        $masked = ($second_byte & 0x80) !== 0;
        $length = $second_byte & 0x7F;

        $offset = 2;

        if ($length === 126) {
            if (strlen($buffer) < $offset + 2) {
                break;
            }

            $length = unpack('n', substr($buffer, $offset, 2))[1];
            $offset += 2;
        } elseif ($length === 127) {
            if (strlen($buffer) < $offset + 8) {
                break;
            }

            $length = unpack('J', substr($buffer, $offset, 8))[1];
            $offset += 8;
        }

        // PHP has no unsigned 64-bit int, so a 'J' length with the high bit set
        // decodes to a NEGATIVE int - and no legitimate client frame exceeds
        // our per-connection buffer cap anyway. Either is a protocol violation.
        // Without this, a negative length makes the completeness check below
        // pass and `substr($buffer, $offset + $length)` rewind to the same
        // bytes, spinning this while(true) forever and hanging the whole
        // single-process daemon. Flag it so the caller drops the connection.
        if ($length < 0 || $length > MAX_CLIENT_BUFFER_BYTES) {
            return ['frames' => $frames, 'buffer' => '', 'error' => true];
        }

        $mask_key = '';

        if ($masked) {
            if (strlen($buffer) < $offset + 4) {
                break;
            }

            $mask_key = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if (strlen($buffer) < $offset + $length) {
            break;
        }

        $payload = substr($buffer, $offset, $length);

        if ($masked) {
            $unmasked = '';

            for ($i = 0; $i < $length; $i++) {
                $unmasked .= $payload[$i] ^ $mask_key[$i % 4];
            }

            $payload = $unmasked;
        }

        $frames[] = ['fin' => $fin, 'opcode' => $opcode, 'payload' => $payload];
        $buffer = substr($buffer, $offset + $length);
    }

    return ['frames' => $frames, 'buffer' => $buffer];
}

// ---------- Handshake ----------

function try_complete_handshake(int $id): bool
{
    global $connections, $ws_secret, $connectionsByUser;

    $header_end = strpos($connections[$id]['recvBuffer'], "\r\n\r\n");

    if ($header_end === false) {
        return false; // more data needed
    }

    $header_text = substr($connections[$id]['recvBuffer'], 0, $header_end);
    $connections[$id]['recvBuffer'] = substr($connections[$id]['recvBuffer'], $header_end + 4);

    $lines = explode("\r\n", $header_text);
    $request_line = array_shift($lines);

    if (!preg_match('#^GET\s+(\S+)\s+HTTP/1\.1$#i', $request_line, $request_match)) {
        drop_connection($id);

        return false;
    }

    $headers = [];

    foreach ($lines as $line) {
        $colon = strpos($line, ':');

        if ($colon === false) {
            continue;
        }

        $headers[strtolower(trim(substr($line, 0, $colon)))] = trim(substr($line, $colon + 1));
    }

    $key = $headers['sec-websocket-key'] ?? null;

    if (
        $key === null
        || strtolower($headers['upgrade'] ?? '') !== 'websocket'
        || stripos($headers['connection'] ?? '', 'upgrade') === false
    ) {
        drop_connection($id);

        return false;
    }

    $path = parse_url($request_match[1], PHP_URL_PATH) ?? '/';
    $query_string = parse_url($request_match[1], PHP_URL_QUERY) ?? '';
    parse_str($query_string, $query_params);

    $token = $query_params['token'] ?? '';
    $user_id = $token !== '' ? WSToken::verify($token, $ws_secret) : null;

    if ($path !== '/' || $user_id === null) {
        $connections[$id]['sendBuffer'] .= "HTTP/1.1 401 Unauthorized\r\nConnection: close\r\n\r\n";
        $connections[$id]['handshakeDone'] = true; // let the 401 flush, then close on write-complete
        $connections[$id]['closeAfterFlush'] = true;

        return true;
    }

    $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

    $connections[$id]['sendBuffer'] .= "HTTP/1.1 101 Switching Protocols\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Accept: " . $accept . "\r\n\r\n";

    $connections[$id]['handshakeDone'] = true;
    attach_user($id, $user_id);

    log_line('Client connected: user ' . $user_id . ' (connection ' . count($connectionsByUser[$user_id] ?? []) . ')');

    return true;
}

// ---------- Push channel ----------

function handle_push_request(int $id): void
{
    global $connections, $connectionsByUser, $ws_secret;

    $newline_pos = strpos($connections[$id]['recvBuffer'], "\n");

    if ($newline_pos === false) {
        return; // more data needed
    }

    $line = substr($connections[$id]['recvBuffer'], 0, $newline_pos);
    $request = json_decode($line, true);

    $delivered = 0;

    if (
        is_array($request)
        && isset($request['secret'], $request['userId'], $request['payload'])
        && is_string($request['secret'])
        // No secret configured (null) - reject every push rather than let
        // hash_equals be called with a null expected value.
        && is_string($ws_secret) && $ws_secret !== ''
        && hash_equals($ws_secret, $request['secret'])
    ) {
        $target_user_id = (int) $request['userId'];
        $encoded_payload = json_encode($request['payload']);
    } else {
        $encoded_payload = false;
    }

    // json_encode() can return false (e.g. a value that only serializes to
    // INF/NAN) - ws_encode_text_frame() takes a strictly-typed string, so
    // passing false straight through would throw and take the whole
    // single-process daemon down with it. Just deliver nothing instead.
    if ($encoded_payload !== false) {
        $frame = ws_encode_text_frame($encoded_payload);

        foreach ($connectionsByUser[$target_user_id] ?? [] as $client_id) {
            if (($connections[$client_id]['kind'] ?? null) !== 'client') {
                continue;
            }

            // A client that has stopped reading (dead NAT mapping, frozen
            // tab) never drains its sendBuffer - drop it rather than letting
            // every future push for that user pile up in memory forever.
            if (strlen($connections[$client_id]['sendBuffer']) + strlen($frame) > MAX_SEND_BUFFER_BYTES) {
                drop_connection($client_id);
                continue;
            }

            $connections[$client_id]['sendBuffer'] .= $frame;
            $delivered++;
        }
    }

    $connections[$id]['sendBuffer'] .= json_encode(['delivered' => $delivered]) . "\n";
    $connections[$id]['closeAfterFlush'] = true;
}

// ---------- Client data frame handling ----------

function handle_client_frames(int $id): void
{
    global $connections;

    $result = ws_decode_frames($connections[$id]['recvBuffer']);

    // A malformed/oversized frame length is a protocol violation - drop it.
    if (!empty($result['error'])) {
        drop_connection($id);

        return;
    }

    $connections[$id]['recvBuffer'] = $result['buffer'];

    foreach ($result['frames'] as $frame) {
        switch ($frame['opcode']) {
            case 0x8: // close
                $connections[$id]['sendBuffer'] .= ws_encode_close_frame();
                $connections[$id]['closeAfterFlush'] = true;
                return;

            case 0x9: // ping
                // Guard the reply path with the same send-buffer cap the push
                // path uses: a client that floods pings while never draining
                // its socket would otherwise pile up un-flushable pongs until
                // the daemon runs out of memory.
                $pong = ws_encode_pong_frame($frame['payload']);

                if (strlen($connections[$id]['sendBuffer']) + strlen($pong) > MAX_SEND_BUFFER_BYTES) {
                    drop_connection($id);

                    return;
                }

                $connections[$id]['sendBuffer'] .= $pong;
                break;

            case 0xA: // pong
                break;

            case 0x1: // text
            case 0x2: // binary
                if (!$frame['fin']) {
                    $connections[$id]['fragOpcode'] = $frame['opcode'];
                    $connections[$id]['fragBuffer'] = $frame['payload'];
                }
                // Complete, unfragmented application messages aren't
                // currently used for anything client -> server - this is a
                // push-only channel from the app's point of view - so a
                // finished text message is simply discarded.
                break;

            case 0x0: // continuation
                $connections[$id]['fragBuffer'] .= $frame['payload'];

                // fragBuffer accumulates across many separate frames, so the
                // per-read recvBuffer cap alone doesn't bound it.
                if (strlen($connections[$id]['fragBuffer']) > MAX_CLIENT_BUFFER_BYTES) {
                    drop_connection($id);

                    return;
                }

                if ($frame['fin']) {
                    $connections[$id]['fragOpcode'] = null;
                    $connections[$id]['fragBuffer'] = '';
                }

                break;
        }
    }
}

// ---------- Event loop ----------

while (true) {
    // Reassure the systemd watchdog each pass; if the loop ever hangs and stops
    // reaching this point, the ping stops and systemd restarts the service.
    if ($watchdog_interval > 0 && microtime(true) - $last_watchdog_ping >= $watchdog_interval) {
        sd_notify('WATCHDOG=1');
        $last_watchdog_ping = microtime(true);
    }

    $read = [$public_listener, $push_listener];
    $write = [];

    foreach ($connections as $id => $connection) {
        $read[] = $connection['socket'];

        if ($connection['sendBuffer'] !== '') {
            $write[] = $connection['socket'];
        }
    }

    $except = null;
    $changed = stream_select($read, $write, $except, 5);

    if ($changed === false) {
        continue;
    }

    reap_stale_connections();

    foreach ($read as $socket) {
        if ($socket === $public_listener) {
            $client = @stream_socket_accept($public_listener, 0);

            if ($client !== false) {
                register_connection($client, 'client');
            }

            continue;
        }

        if ($socket === $push_listener) {
            $client = @stream_socket_accept($push_listener, 0);

            if ($client !== false) {
                register_connection($client, 'push');
            }

            continue;
        }

        $id = (int) $socket;

        if (!isset($connections[$id])) {
            continue;
        }

        if ($use_tls && $connections[$id]['kind'] === 'client' && !$connections[$id]['tlsReady']) {
            $enabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_SERVER);

            if ($enabled === true) {
                $connections[$id]['tlsReady'] = true;
            } elseif ($enabled === false) {
                $ssl_error = error_get_last();
                log_line('TLS handshake failed for connection ' . $id . ': ' . ($ssl_error['message'] ?? 'unknown error'));
                drop_connection($id);
            }

            continue; // wait for more data either way
        }

        $chunk = @fread($socket, 65536);

        if ($chunk === false || $chunk === '') {
            drop_connection($id);
            continue;
        }

        $connections[$id]['recvBuffer'] .= $chunk;
        $connections[$id]['lastActivityAt'] = microtime(true);
        $connections[$id]['pingSentAt'] = null;

        $recv_cap = $connections[$id]['kind'] === 'push' ? MAX_PUSH_BUFFER_BYTES : MAX_CLIENT_BUFFER_BYTES;

        if (strlen($connections[$id]['recvBuffer']) > $recv_cap) {
            drop_connection($id);
            continue;
        }

        if ($connections[$id]['kind'] === 'push') {
            handle_push_request($id);
        } elseif (!$connections[$id]['handshakeDone']) {
            try_complete_handshake($id);
        } else {
            handle_client_frames($id);
        }
    }

    foreach ($write as $socket) {
        $id = (int) $socket;

        if (!isset($connections[$id]) || $connections[$id]['sendBuffer'] === '') {
            continue;
        }

        $written = @fwrite($socket, $connections[$id]['sendBuffer']);

        if ($written === false) {
            drop_connection($id);
            continue;
        }

        $connections[$id]['sendBuffer'] = substr($connections[$id]['sendBuffer'], $written);

        if ($connections[$id]['sendBuffer'] === '' && ($connections[$id]['closeAfterFlush'] ?? false)) {
            drop_connection($id);
        }
    }
}
