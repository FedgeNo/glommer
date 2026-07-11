<?php

declare(strict_types=1);

/**
 * The environment prerequisites the app needs regardless of database
 * configuration - shared between `bin/install.php` (CLI, stops at the first
 * failure) and the web setup wizard (which shows every current failure at
 * once, since there's no terminal to re-run against).
 */
class EnvironmentChecker
{
    // Audio and video uploads routinely exceed PHP's stock 2M upload cap, and
    // camera-roll dumps of many files exceed the stock 8M request cap and the
    // 20-file count cap. The shipped .user.ini raises the first two; these are
    // the floors the checks enforce, so a host that overrides .user.ini (or has
    // it disabled) is flagged rather than silently rejecting or truncating
    // uploads. MIN_FILE_UPLOADS is checked separately because max_file_uploads
    // is PHP_INI_SYSTEM and can only be raised in php.ini, not .user.ini.
    private const MIN_UPLOAD_BYTES = 256 * 1024 * 1024;
    private const MIN_UPLOAD_LABEL = '256M';
    private const MIN_FILE_UPLOADS = 100;

    /**
     * @return array<string, array{ok: bool, message: string}>
     */
    public static function checks(): array
    {
        return [
            'PHP version' => self::checkPHPVersion(),
            'PHP extensions' => self::checkExtensions(),
            'Shell execution & media binaries' => self::checkShellAndFfmpeg(),
            'Upload size limits' => self::checkUploadLimits(),
            'Upload file count' => self::checkMaxFileUploads(),
            'Temp directory' => self::checkTempDirectory(),
            'Upload directories' => self::checkUploadDirectories(),
            'SELinux' => self::checkSELinux(),
            'Outbound network' => self::checkOutboundNetwork(),
            'WebSocket server' => self::checkWebSocketServer(),
            'Backups' => self::checkBackups(),
        ];
    }

    /**
     * A functional check, like the WebSocket one above: not "is BACKUP_DIR
     * set" but "has a backup actually completed" - proof the mechanism really
     * works, not just that it's configured. An install with no working backup
     * is not production-ready, so this blocks the same as every other
     * environment prerequisite.
     *
     * @return array{ok: bool, message: string}
     */
    private static function checkBackups(): array
    {
        if (Backup::hasCompletedRun()) {
            return ['ok' => true, 'message' => 'a completed backup exists in ' . Backup::rootDir()];
        }

        return ['ok' => false, 'message' => 'No completed backup found in ' . Backup::rootDir() . '. Run "php bin/backup.php" once to create one and prove the mechanism works, then set up recurring backups (a systemd timer - see README.md\'s Backups section) so this keeps being true.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkUploadLimits(): array
    {
        if (PHP_SAPI !== 'cli') {
            // Web SAPI: .user.ini has actually been applied, so ini_get() reflects reality directly.
            $problems = self::uploadLimitProblems(
                self::iniBytes((string) ini_get('upload_max_filesize')),
                self::iniBytes((string) ini_get('post_max_size')),
                (string) ini_get('upload_max_filesize'),
                (string) ini_get('post_max_size')
            );

            if ($problems !== []) {
                return ['ok' => false, 'message' => implode(' ', $problems) . ' Set these in .user.ini (shipped with the app) or php.ini, then reload PHP-FPM.'];
            }

            return ['ok' => true, 'message' => 'upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size')];
        }

        // .user.ini (the file this project ships these values in) is only
        // applied by the FPM/CGI web SAPI - the CLI SAPI never reads it, so
        // ini_get() under CLI reports unrelated values that have nothing to
        // do with what the web server will actually enforce. Parse the real
        // file on disk (the same one the web server reads) to check its
        // declared values, then confirm live (see liveIniValues()) that the
        // web server is actually applying them - the file being correct
        // doesn't prove .user.ini support is even enabled for the pool.
        $user_ini_path = __DIR__ . '/../../.user.ini';

        if (!is_file($user_ini_path)) {
            return ['ok' => false, 'message' => '.user.ini not found at ' . $user_ini_path . ' - the web server would have no upload_max_filesize/post_max_size override. Restore the shipped .user.ini (upload_max_filesize = 2G, post_max_size = 8G).'];
        }

        $directives = @parse_ini_file($user_ini_path);

        if ($directives === false) {
            return ['ok' => false, 'message' => 'Could not parse ' . $user_ini_path . ' - check it for syntax errors.'];
        }

        $declared_upload_max = (string) ($directives['upload_max_filesize'] ?? '(not set)');
        $declared_post_max = (string) ($directives['post_max_size'] ?? '(not set)');
        $problems = self::uploadLimitProblems(
            self::iniBytes($declared_upload_max),
            self::iniBytes($declared_post_max),
            $declared_upload_max,
            $declared_post_max
        );

        if ($problems !== []) {
            return ['ok' => false, 'message' => implode(' ', $problems) . ' Set these in .user.ini at the project root. (Checked by parsing .user.ini directly, since the CLI SAPI never applies it itself.)'];
        }

        $live = self::liveIniValues();

        if ($live === null) {
            return ['ok' => true, 'message' => '.user.ini declares upload_max_filesize=' . $declared_upload_max . ', post_max_size=' . $declared_post_max . ' (could not reach http://127.0.0.1/ to confirm the web server is actually applying it - re-run once the web server is up, or check via the web setup wizard directly)'];
        }

        if (self::iniBytes($live['uploadMaxFilesize']) !== self::iniBytes($declared_upload_max) || self::iniBytes($live['postMaxSize']) !== self::iniBytes($declared_post_max)) {
            return ['ok' => false, 'message' => '.user.ini declares upload_max_filesize=' . $declared_upload_max . ', post_max_size=' . $declared_post_max . ' but the web server is actually applying upload_max_filesize=' . $live['uploadMaxFilesize'] . ', post_max_size=' . $live['postMaxSize'] . ' - .user.ini isn\'t being honored (check user_ini.filename/user_ini.cache_ttl in the web SAPI\'s own php.ini - these are PHP_INI_SYSTEM settings the FPM pool can set independently of the CLI - or that PHP-FPM has had time to pick up the file).'];
        }

        return ['ok' => true, 'message' => 'upload_max_filesize=' . $live['uploadMaxFilesize'] . ', post_max_size=' . $live['postMaxSize'] . ' (confirmed live via the web server, matches .user.ini)'];
    }

    /**
     * @return string[]
     */
    private static function uploadLimitProblems(int $upload_max, int $post_max, string $upload_max_label, string $post_max_label): array
    {
        $problems = [];

        if ($upload_max < self::MIN_UPLOAD_BYTES) {
            $problems[] = 'upload_max_filesize is ' . $upload_max_label . ' - audio and video files routinely exceed that and would be rejected before any code runs. Raise it to at least ' . self::MIN_UPLOAD_LABEL . '.';
        }

        // post_max_size caps the entire request, so it must be at least as large
        // as a single upload (0 means unlimited, which is fine).
        if ($post_max !== 0 && ($post_max < $upload_max || $post_max < self::MIN_UPLOAD_BYTES)) {
            $problems[] = 'post_max_size (' . $post_max_label . ') must be at least upload_max_filesize and ' . self::MIN_UPLOAD_LABEL . ' or larger.';
        }

        return $problems;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkMaxFileUploads(): array
    {
        if (PHP_SAPI !== 'cli') {
            $max = (int) ini_get('max_file_uploads');

            return self::maxFileUploadsResult($max === 0 ? 'unlimited' : (string) $max, $max);
        }

        // max_file_uploads is PHP_INI_SYSTEM - unlike upload_max_filesize/
        // post_max_size there's no project-shipped file to parse (it can't go
        // in .user.ini at all), so there's nothing to check from the CLI
        // except a live request to the web server itself.
        $live = self::liveIniValues();

        if ($live === null) {
            return ['ok' => true, 'message' => 'max_file_uploads - could not reach http://127.0.0.1/ to check it live. Set it for the web server yourself: max_file_uploads = 100 or higher in php.ini or the PHP-FPM pool config (it is PHP_INI_SYSTEM and cannot be set in .user.ini), then reload PHP-FPM. Re-run once the web server is up, or check via the web setup wizard directly.'];
        }

        $max = $live['maxFileUploads'] === '0' ? 0 : (int) $live['maxFileUploads'];

        return self::maxFileUploadsResult($live['maxFileUploads'] === '0' ? 'unlimited' : $live['maxFileUploads'], $max, live: true);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function maxFileUploadsResult(string $label, int $max, bool $live = false): array
    {
        // A camera-roll dump of mixed photos and video easily passes the
        // stock limit of 20, and every file over the limit is silently
        // dropped from the request. 0 means unlimited, which is fine.
        if ($max !== 0 && $max < self::MIN_FILE_UPLOADS) {
            return ['ok' => false, 'message' => 'max_file_uploads is ' . $label . ($live ? ' (confirmed live via the web server)' : '') . ' - a large multi-file post (a camera-roll dump) would be silently truncated to that many files. Raise it to at least ' . self::MIN_FILE_UPLOADS . ' in php.ini or the PHP-FPM pool (it is PHP_INI_SYSTEM and cannot be set in .user.ini), then reload PHP-FPM.'];
        }

        return ['ok' => true, 'message' => 'max_file_uploads = ' . $label . ($live ? ' (confirmed live via the web server)' : '')];
    }

    /**
     * Fetches the web SAPI's actual, resolved ini values via a real HTTP
     * request to the site's own environment-check endpoint (127.0.0.1, plain
     * HTTP - before install this may be all that's reachable, since TLS/vhost
     * setup may not exist yet; deliberately not SafeHTTPFetcher, which
     * refuses loopback addresses by design - this is an intentional loopback
     * probe, not a fetch of untrusted user input). Returns null if the site
     * isn't reachable this way (e.g. the web server isn't running yet), which
     * callers treat as inconclusive, not a failure.
     *
     * @return array{uploadMaxFilesize: string, postMaxSize: string, maxFileUploads: string}|null
     */
    private static function liveIniValues(): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://127.0.0.1/environment-check',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $status !== 200) {
            return null;
        }

        $data = json_decode($body, true);

        if (
            !is_array($data)
            || !isset($data['response']['uploadMaxFilesize'], $data['response']['postMaxSize'], $data['response']['maxFileUploads'])
        ) {
            return null;
        }

        return [
            'uploadMaxFilesize' => (string) $data['response']['uploadMaxFilesize'],
            'postMaxSize' => (string) $data['response']['postMaxSize'],
            'maxFileUploads' => (string) $data['response']['maxFileUploads'],
        ];
    }

    /**
     * Converts a PHP ini shorthand size (e.g. "64M", "80M", "1G", "512K") to a
     * byte count. An empty/zero value returns 0 (meaning "unlimited" for
     * post_max_size).
     */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (int) $value;
        $unit = strtolower($value[strlen($value) - 1]);

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkPHPVersion(): array
    {
        if (PHP_VERSION_ID < 80100) {
            return ['ok' => false, 'message' => 'PHP 8.1 or newer is required - this is PHP ' . PHP_VERSION . '.'];
        }

        return ['ok' => true, 'message' => 'PHP ' . PHP_VERSION];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkExtensions(): array
    {
        $required_extensions = [
            'mysqli' => 'the database layer',
            'gd' => 'image resizing and thumbnails',
            'curl' => 'link preview fetching',
            'dom' => 'HTML rendering and link preview parsing',
            'libxml' => 'HTML parsing',
            'fileinfo' => 'upload type detection',
            'mbstring' => 'multibyte-safe text handling',
        ];

        $missing = [];

        foreach ($required_extensions as $extension => $used_for) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension . ' (' . $used_for . ')';
            }
        }

        if ($missing !== []) {
            return ['ok' => false, 'message' => 'Missing PHP extension(s): ' . implode(', ', $missing) . '. Install them (e.g. php-<name>) and restart PHP.'];
        }

        return ['ok' => true, 'message' => 'all required PHP extensions loaded (' . implode(', ', array_keys($required_extensions)) . ')'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkShellAndFfmpeg(): array
    {
        $disabled_functions = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        foreach (['exec', 'shell_exec'] as $function) {
            if (!function_exists($function) || in_array($function, $disabled_functions, true)) {
                return ['ok' => false, 'message' => $function . '() is disabled in this PHP configuration - video/audio processing runs ffmpeg through it. Remove it from disable_functions.'];
            }
        }

        foreach (['ffmpeg', 'ffprobe'] as $binary) {
            if (trim((string) shell_exec('command -v ' . $binary . ' 2>/dev/null')) === '') {
                return ['ok' => false, 'message' => $binary . ' was not found on PATH - video and audio uploads cannot be processed without it. Install ffmpeg.'];
            }
        }

        return ['ok' => true, 'message' => 'exec()/shell_exec() available, ffmpeg and ffprobe found'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkTempDirectory(): array
    {
        if (!is_writable(sys_get_temp_dir())) {
            return ['ok' => false, 'message' => 'The system temp directory (' . sys_get_temp_dir() . ') is not writable - file uploads and link preview images stage through it.'];
        }

        return ['ok' => true, 'message' => 'temp directory writable (' . sys_get_temp_dir() . ')'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkUploadDirectories(): array
    {
        $upload_dirs = [
            __DIR__ . '/../../uploads',
            __DIR__ . '/../../uploads/avatars',
            __DIR__ . '/../../uploads/private',
            __DIR__ . '/../../uploads/private/originals',
            __DIR__ . '/../../uploads/private/pending',
        ];

        foreach ($upload_dirs as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                return ['ok' => false, 'message' => 'Could not create ' . dirname($dir) . '/' . basename($dir) . ' - create it manually and make it writable.'];
            }

            if (!is_writable($dir)) {
                return ['ok' => false, 'message' => realpath($dir) . ' is not writable by this user. It (and everything under it) must be writable by the web server user too - uploads are processed and stored there.'];
            }
        }

        return ['ok' => true, 'message' => 'upload directories exist and are writable'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkSELinux(): array
    {
        if (trim((string) shell_exec('command -v getenforce 2>/dev/null')) === '') {
            return ['ok' => true, 'message' => 'SELinux not present, skipped'];
        }

        if (trim((string) shell_exec('getenforce 2>/dev/null')) !== 'Enforcing') {
            return ['ok' => true, 'message' => 'SELinux not enforcing'];
        }

        $bool_output = trim((string) shell_exec('getsebool httpd_can_network_connect 2>/dev/null'));

        if (str_contains($bool_output, '--> off')) {
            return ['ok' => false, 'message' => 'SELinux is enforcing and httpd_can_network_connect is off - the web server cannot make any outbound request, which breaks link previews. Fix with: setsebool -P httpd_can_network_connect on'];
        }

        return ['ok' => true, 'message' => 'SELinux enforcing, httpd_can_network_connect on'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkOutboundNetwork(): array
    {
        if (SafeHTTPFetcher::get('https://example.com/', 65536) === null) {
            return ['ok' => false, 'message' => 'An outbound HTTPS request failed - link previews need internet access. Common causes: a firewall blocking egress, no DNS resolution for the PHP process, or a container with no network.'];
        }

        return ['ok' => true, 'message' => 'outbound HTTPS requests work'];
    }

    /**
     * Live notifications and messages are a primary feature, not an optional
     * extra - this performs a real HTTP-upgrade handshake and a ping/pong
     * frame round trip against bin/websocket-server.php (a separate,
     * long-running process from Apache/PHP-FPM that must already be started
     * - see the systemd unit under README.md), not just a port-open check.
     *
     * @return array{ok: bool, message: string}
     */
    private static function checkWebSocketServer(): array
    {
        $config = require __DIR__ . '/../config.php';

        // With WS_TLS_CERT/WS_TLS_KEY configured the daemon listens over TLS
        // (browsers on an https page require wss://), so this check has to
        // speak TLS too. Verification is off: this is a loopback reachability
        // check, not a trust decision, and the certificate's name is the
        // public hostname, not 127.0.0.1.
        $daemon_uses_tls = ($config['WSTLSCert'] ?? null) !== null && ($config['WSTLSKey'] ?? null) !== null;

        $context = stream_context_create($daemon_uses_tls ? ['ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ]] : []);

        $transport = $daemon_uses_tls ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client($transport . '127.0.0.1:' . $config['WSPort'], $error_code, $error_message, 3, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            return ['ok' => false, 'message' => 'Could not connect to the WebSocket server on 127.0.0.1:' . $config['WSPort'] . ($daemon_uses_tls ? ' over TLS' : '') . ' (' . $error_message . '). Start it first: systemctl --user start glommer-websocket (see README.md for the unit file). If it just gained or lost WS_TLS_CERT/WS_TLS_KEY, restart it so it matches .env.'];
        }

        stream_set_timeout($socket, 3);

        $token = WSToken::issue(0);
        $key = base64_encode(random_bytes(16));

        fwrite($socket, "GET /?token=" . $token . " HTTP/1.1\r\nHost: 127.0.0.1\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: " . $key . "\r\nSec-WebSocket-Version: 13\r\n\r\n");

        $response = fread($socket, 1024);
        $expected_accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        if ($response === false || !str_contains($response, '101 Switching Protocols') || !str_contains($response, $expected_accept)) {
            fclose($socket);

            return ['ok' => false, 'message' => 'The service on 127.0.0.1:' . $config['WSPort'] . ' did not complete a valid WebSocket handshake - something other than bin/websocket-server.php may be listening on that port.'];
        }

        $mask_key = random_bytes(4);
        $ping_payload = 'ping';
        $masked_ping = '';

        for ($i = 0; $i < strlen($ping_payload); $i++) {
            $masked_ping .= $ping_payload[$i] ^ $mask_key[$i % 4];
        }

        fwrite($socket, chr(0x89) . chr(0x80 | strlen($ping_payload)) . $mask_key . $masked_ping);
        $frame_header = fread($socket, 2);
        $is_pong = strlen($frame_header) === 2 && (ord($frame_header[0]) & 0x0F) === 0xA;

        fclose($socket);

        if (!$is_pong) {
            return ['ok' => false, 'message' => 'The WebSocket server accepted the handshake but did not respond to a ping - it may be stuck or misbehaving. Check its logs.'];
        }

        return ['ok' => true, 'message' => 'WebSocket server reachable and responding on 127.0.0.1:' . $config['WSPort']];
    }
}
