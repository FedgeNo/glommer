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
    $file = __DIR__ . '/../src/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

function log_line(string $message): void
{
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n");
}

$config = require __DIR__ . '/../src/config.php';

$use_tls = $config['WSTLSCert'] !== null && $config['WSTLSKey'] !== null;

$context = stream_context_create();

if ($use_tls) {
    stream_context_set_option($context, 'ssl', 'local_cert', $config['WSTLSCert']);
    stream_context_set_option($context, 'ssl', 'local_pk', $config['WSTLSKey']);
    stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
    stream_context_set_option($context, 'ssl', 'verify_peer', false);
}

$public_listener = stream_socket_server(
    'tcp://' . $config['WSHost'] . ':' . $config['WSPort'],
    $error_code,
    $error_message,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);

if ($public_listener === false) {
    log_line('Could not bind public WebSocket listener on ' . $config['WSHost'] . ':' . $config['WSPort'] . ' - ' . $error_message);
    exit(1);
}

// Loopback only - this port is never meant to be reachable from outside the
// machine, api/*.php scripts are its only legitimate callers.
$push_listener = stream_socket_server(
    'tcp://127.0.0.1:' . $config['WSPushPort'],
    $error_code,
    $error_message,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);

if ($push_listener === false) {
    log_line('Could not bind internal push listener on 127.0.0.1:' . $config['WSPushPort'] . ' - ' . $error_message);
    exit(1);
}

stream_set_blocking($public_listener, false);
stream_set_blocking($push_listener, false);

log_line('Listening: public ws' . ($use_tls ? 's' : '') . '://' . $config['WSHost'] . ':' . $config['WSPort'] . ', internal push 127.0.0.1:' . $config['WSPushPort']);

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

function register_connection($socket, string $kind): int
{
    global $connections;

    stream_set_blocking($socket, false);
    $id = (int) $socket;

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
    ];

    return $id;
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
    global $connections, $config, $connectionsByUser;

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
    $user_id = $token !== '' ? WSToken::verify($token, $config['WSSecret']) : null;

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
    global $connections, $connectionsByUser, $config;

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
        && hash_equals($config['WSSecret'], $request['secret'])
    ) {
        $target_user_id = (int) $request['userId'];
        $frame = ws_encode_text_frame(json_encode($request['payload']));

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
    $connections[$id]['recvBuffer'] = $result['buffer'];

    foreach ($result['frames'] as $frame) {
        switch ($frame['opcode']) {
            case 0x8: // close
                $connections[$id]['sendBuffer'] .= ws_encode_close_frame();
                $connections[$id]['closeAfterFlush'] = true;
                return;

            case 0x9: // ping
                $connections[$id]['sendBuffer'] .= ws_encode_pong_frame($frame['payload']);
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
            $enabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_SERVER);

            if ($enabled === true) {
                $connections[$id]['tlsReady'] = true;
            } elseif ($enabled === false) {
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
