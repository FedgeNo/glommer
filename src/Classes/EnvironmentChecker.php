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
            'WebSocket service persistence' => self::checkWebSocketServicePersistence(),
            'Backups' => self::checkBackups(),
            'Backup timer persistence' => self::checkBackupTimerPersistence(),
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
     * A separate concern from checkBackups(): that check proves a backup
     * mechanism *works*, not that anything will run it *again*. Someone could
     * run `php bin/backup.php` by hand once (satisfying checkBackups()) and
     * never actually schedule a recurring one - this catches that. Only
     * meaningful in a CLI context (systemd --user is tied to the Unix user
     * running this script, not to a PHP SAPI - there's nothing to check here
     * under the web SAPI).
     *
     * @return array{ok: bool, message: string}
     */
    private static function checkBackupTimerPersistence(): array
    {
        if (PHP_SAPI !== 'cli') {
            return ['ok' => true, 'message' => 'not applicable under the web SAPI'];
        }

        if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
            return ['ok' => true, 'message' => 'systemctl not found - assuming a non-systemd scheduler (cron or otherwise) is set up separately'];
        }

        $timer_status = trim((string) shell_exec('systemctl --user is-enabled glommer-backup.timer 2>/dev/null'));

        if ($timer_status !== 'enabled') {
            return ['ok' => false, 'message' => 'glommer-backup.timer is not enabled - a backup may have run once, but nothing is scheduled to run one again. Run "php bin/backup.php" then set up the recurring timer (see README.md\'s Backups section).'];
        }

        $user = trim((string) shell_exec('id -un 2>/dev/null')) ?: (get_current_user() ?: (string) getenv('USER'));
        $linger_status = strtolower(trim((string) shell_exec('loginctl show-user ' . escapeshellarg($user) . ' --property=Linger 2>/dev/null')));

        if (!str_contains($linger_status, 'yes')) {
            return ['ok' => false, 'message' => 'glommer-backup.timer is enabled, but lingering is not enabled for ' . $user . ' - the timer stops firing as soon as this user logs out, and won\'t survive a reboot. Run: sudo loginctl enable-linger ' . $user];
        }

        return ['ok' => true, 'message' => 'glommer-backup.timer is enabled, lingering is on for ' . $user];
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
        // declared values, then confirm live (see liveFacts()) that the
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

        $live = self::liveFacts();

        if ($live === null || !isset($live['uploadMaxFilesize'], $live['postMaxSize'])) {
            return ['ok' => true, 'message' => '.user.ini declares upload_max_filesize=' . $declared_upload_max . ', post_max_size=' . $declared_post_max . ' (could not reach http://127.0.0.1/ to confirm the web server is actually applying it - re-run once the web server is up, or check via the web setup wizard directly)'];
        }

        $live_upload_max = (string) $live['uploadMaxFilesize'];
        $live_post_max = (string) $live['postMaxSize'];

        if (self::iniBytes($live_upload_max) !== self::iniBytes($declared_upload_max) || self::iniBytes($live_post_max) !== self::iniBytes($declared_post_max)) {
            return ['ok' => false, 'message' => '.user.ini declares upload_max_filesize=' . $declared_upload_max . ', post_max_size=' . $declared_post_max . ' but the web server is actually applying upload_max_filesize=' . $live_upload_max . ', post_max_size=' . $live_post_max . ' - .user.ini isn\'t being honored (check user_ini.filename/user_ini.cache_ttl in the web SAPI\'s own php.ini - these are PHP_INI_SYSTEM settings the FPM pool can set independently of the CLI - or that PHP-FPM has had time to pick up the file).'];
        }

        return ['ok' => true, 'message' => 'upload_max_filesize=' . $live_upload_max . ', post_max_size=' . $live_post_max . ' (confirmed live via the web server, matches .user.ini)'];
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
        $live = self::liveFacts();

        if ($live === null || !isset($live['maxFileUploads'])) {
            return ['ok' => true, 'message' => 'max_file_uploads - could not reach http://127.0.0.1/ to check it live. Set it for the web server yourself: max_file_uploads = 100 or higher in php.ini or the PHP-FPM pool config (it is PHP_INI_SYSTEM and cannot be set in .user.ini), then reload PHP-FPM. Re-run once the web server is up, or check via the web setup wizard directly.'];
        }

        $live_max_label = (string) $live['maxFileUploads'];
        $max = $live_max_label === '0' ? 0 : (int) $live_max_label;

        return self::maxFileUploadsResult($live_max_label === '0' ? 'unlimited' : $live_max_label, $max, live: true);
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
     * Whether a real HTTPS connection to the site's actual configured
     * hostname (not 127.0.0.1 - a named-vhost setup may not route the
     * loopback address to this site at all, and testing the real hostname is
     * what actually matters for "does https:// on this domain really work")
     * succeeds. Proves SITE_URL's https:// prefix isn't just a string nobody's
     * backed with a working certificate/vhost, the way the naive check (does
     * the string start with "https://"?) never could. SSL verification is
     * off: this confirms *something* answers with TLS at all, not that the
     * certificate is CA-trusted (a self-signed/mkcert dev cert is fine here) -
     * same reasoning as the WebSocket TLS check and fetchLiveFacts() above.
     *
     * Returns true if an HTTPS response comes back, false if a connection
     * happens but TLS itself demonstrably fails (a real, provable problem -
     * something's listening on the port but isn't actually serving TLS), and
     * null if nothing could be reached there at all (inconclusive - DNS not
     * pointed here yet, a firewall, or the web server simply isn't up yet
     * during install).
     */
    public static function httpsServing(string $host): ?bool
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://' . $host . '/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);

        $body = curl_exec($curl);
        $errno = curl_errno($curl);
        curl_close($curl);

        if ($body !== false) {
            return true;
        }

        // CURLE_SSL_CONNECT_ERROR (35) and friends mean a TCP connection was
        // established but the TLS handshake itself failed - real proof
        // something's listening on the port without actually serving TLS.
        // CURLE_COULDNT_CONNECT (7), CURLE_COULDNT_RESOLVE_HOST (6), and
        // timeouts mean nothing answered at all, which is inconclusive (DNS,
        // a firewall, or the web server just isn't up yet), not a
        // demonstrated failure.
        return in_array($errno, [CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CIPHER, CURLE_SSL_PEER_CERTIFICATE], true)
            ? false
            : null;
    }

    /**
     * Proves - rather than asks about - whether Apache's HTTPS redirect
     * (.htaccess's `RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI}`) can
     * be spoofed via a forged Host header, which is exactly what "ServerName
     * <host>" + "UseCanonicalName On" exist to prevent. Connects to the
     * site's real hostname on the plain-HTTP port (where that redirect rule
     * lives - not 127.0.0.1, for the same VirtualHost-routing reason as
     * httpsServing()) but sends a deliberately forged Host header, then
     * checks whether the redirect's Location target reflects it.
     *
     * Returns true if the forged host leaks into the redirect (proven
     * vulnerable), false if it doesn't (proven safe - ServerName/
     * UseCanonicalName are genuinely working), and null if this can't be
     * tested at all (no reachable response, or no redirect to inspect -
     * e.g. HTTPS isn't enforced yet on this install) - callers fall back to
     * asking when null, since inconclusive isn't the same as safe.
     */
    public static function hostHeaderSpoofable(string $host): ?bool
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $spoofed_host = 'glommer-spoof-test-' . bin2hex(random_bytes(4)) . '.invalid';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://' . $host . '/',
            CURLOPT_HTTPHEADER => ['Host: ' . $spoofed_host],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);

        $headers = curl_exec($curl);
        curl_close($curl);

        if (!is_string($headers) || !preg_match('/^Location:\s*(\S+)/mi', $headers, $match)) {
            return null;
        }

        return str_contains($match[1], $spoofed_host);
    }

    /**
     * Proves a candidate WS_TLS_CERT/WS_TLS_KEY pair actually works, rather
     * than trusting that a cert-generation command exited 0 or that a
     * manually-entered path exists. Checks the certificate and private key
     * are readable, well-formed, and genuinely match each other - the same
     * pairing bin/websocket-server.php loads via 'local_cert'/'local_pk' when
     * it starts. A mismatched, corrupted, or otherwise unusable pair would
     * otherwise only surface once the daemon is restarted and every browser
     * silently fails to open its wss:// connection.
     */
    public static function webSocketCertificateAndKeyMatch(string $cert_path, string $key_path): bool
    {
        if (!is_file($cert_path) || !is_readable($cert_path) || !is_file($key_path) || !is_readable($key_path)) {
            return false;
        }

        $certificate = @file_get_contents($cert_path);
        $private_key = @file_get_contents($key_path);

        if ($certificate === false || $private_key === false) {
            return false;
        }

        $x509 = @openssl_x509_read($certificate);
        $pkey = @openssl_pkey_get_private($private_key);

        if ($x509 === false || $pkey === false) {
            return false;
        }

        return openssl_x509_check_private_key($x509, $pkey);
    }

    /** @var array<string, mixed>|false|null null = not yet fetched, false = fetch failed */
    private static array|false|null $liveFactsCache = null;

    /**
     * Fetches every fact the web SAPI actually resolves - via a real HTTP
     * request to the site's own environment-check endpoint (127.0.0.1, plain
     * HTTP - before install this may be all that's reachable, since TLS/vhost
     * setup may not exist yet; deliberately not SafeHTTPFetcher, which
     * refuses loopback addresses by design - this is an intentional loopback
     * probe, not a fetch of untrusted user input). Cached per-process so the
     * several CLI checks that each need this share one HTTP round trip
     * instead of one apiece. Returns null if the site isn't reachable this
     * way (e.g. the web server isn't running yet), which callers treat as
     * inconclusive, not a failure.
     *
     * @return array<string, mixed>|null
     */
    private static function liveFacts(): ?array
    {
        if (self::$liveFactsCache !== null) {
            return self::$liveFactsCache === false ? null : self::$liveFactsCache;
        }

        self::$liveFactsCache = self::fetchLiveFacts() ?? false;

        return self::$liveFactsCache === false ? null : self::$liveFactsCache;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchLiveFacts(): ?array
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

        if (!is_array($data) || !is_array($data['response'] ?? null)) {
            return null;
        }

        return $data['response'];
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

        if (PHP_SAPI !== 'cli') {
            return ['ok' => true, 'message' => 'PHP ' . PHP_VERSION];
        }

        // CLI: on a host with multiple PHP versions installed, the "php"
        // command on PATH isn't guaranteed to be the same binary the web
        // server's SAPI runs. Confirm live where possible; this one is purely
        // informational either way (the CLI's own version already passed
        // above), never a failure - a real mismatch would show up as its own
        // extension/behavior failures elsewhere in this list.
        $live = self::liveFacts();

        if ($live === null || !isset($live['phpVersion'])) {
            return ['ok' => true, 'message' => 'PHP ' . PHP_VERSION . ' (CLI) - could not confirm the web server\'s version live'];
        }

        if (version_compare((string) $live['phpVersion'], '8.1.0', '<')) {
            return ['ok' => false, 'message' => 'The web server is running PHP ' . $live['phpVersion'] . ' (confirmed live), which is older than the required 8.1 - even though the CLI\'s PHP (' . PHP_VERSION . ') is fine. Upgrade the web server\'s PHP.'];
        }

        return ['ok' => true, 'message' => 'PHP ' . PHP_VERSION . ' (CLI), ' . $live['phpVersion'] . ' (confirmed live via the web server)'];
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

        if (PHP_SAPI !== 'cli') {
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

        // CLI: extension_loaded() reflects the CLI's own php.ini, which a
        // split-package distro (separate php-cli/php-fpm packages, each with
        // their own conf.d) can load independently. Confirm live.
        $live = self::liveFacts();

        if ($live === null || !is_array($live['extensions'] ?? null)) {
            return ['ok' => true, 'message' => 'all required PHP extensions loaded on the CLI, but could not confirm the web server live - check via the web setup wizard directly.'];
        }

        $missing = [];

        foreach ($required_extensions as $extension => $used_for) {
            if (empty($live['extensions'][$extension])) {
                $missing[] = $extension . ' (' . $used_for . ')';
            }
        }

        if ($missing !== []) {
            return ['ok' => false, 'message' => 'The web server is missing PHP extension(s) (confirmed live): ' . implode(', ', $missing) . ' - even though the CLI has them all. Install them for the web SAPI (e.g. php-fpm-<name>) and reload PHP-FPM.'];
        }

        return ['ok' => true, 'message' => 'all required PHP extensions loaded (confirmed live via the web server)'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkShellAndFfmpeg(): array
    {
        if (PHP_SAPI !== 'cli') {
            $disabled_functions = array_map('trim', explode(',', (string) ini_get('disable_functions')));

            foreach (['exec', 'shell_exec'] as $function) {
                if (!function_exists($function) || in_array($function, $disabled_functions, true)) {
                    return ['ok' => false, 'message' => $function . '() is disabled in this PHP configuration - video/audio processing runs ffmpeg through it. Remove it from disable_functions.'];
                }
            }

            foreach (['ffmpeg', 'ffprobe', 'timeout', 'bash'] as $binary) {
                if (trim((string) shell_exec('command -v ' . $binary . ' 2>/dev/null')) === '') {
                    return ['ok' => false, 'message' => $binary . ' was not found on PATH - the media-upload pipeline needs ffmpeg/ffprobe to transcode, plus timeout(1) (coreutils) and bash to run each transcode under wall-clock/CPU/memory limits. Install the missing one.'];
                }
            }

            return ['ok' => true, 'message' => 'exec()/shell_exec() available; ffmpeg, ffprobe, timeout and bash found'];
        }

        // CLI: disable_functions is PHP_INI_SYSTEM and very commonly set
        // DIFFERENTLY between CLI and a hardened FPM pool - exec()/shell_exec()
        // are often disabled specifically for the attacker-reachable web
        // process while staying open for trusted CLI scripts, which is exactly
        // backwards from what matters: api/create-post.php spawns ffmpeg via
        // exec() under the web SAPI, not CLI. PATH is frequently narrower
        // under FPM too. Confirm live rather than assume they match.
        $live = self::liveFacts();

        if ($live === null) {
            return ['ok' => true, 'message' => 'exec()/shell_exec()/ffmpeg OK on the CLI, but could not confirm the web server live - check via the web setup wizard directly, since a hardened FPM pool commonly disables exec()/shell_exec() (or narrows PATH) separately from the CLI.'];
        }

        if (empty($live['execFunctionExists']) || !empty($live['execDisabled'])) {
            return ['ok' => false, 'message' => 'exec() is disabled for the web server (confirmed live) - video/audio processing runs ffmpeg through it. Remove it from disable_functions for the web SAPI/FPM pool, not just the CLI.'];
        }

        if (empty($live['shellExecFunctionExists']) || !empty($live['shellExecDisabled'])) {
            return ['ok' => false, 'message' => 'shell_exec() is disabled for the web server (confirmed live) - remove it from disable_functions for the web SAPI/FPM pool, not just the CLI.'];
        }

        if (empty($live['ffmpegFound'])) {
            return ['ok' => false, 'message' => 'ffmpeg was not found on the web server\'s PATH (confirmed live), even if it\'s on the CLI\'s PATH - FPM pools often set their own, narrower PATH. Add ffmpeg\'s directory to the FPM pool\'s env[PATH].'];
        }

        if (empty($live['ffprobeFound'])) {
            return ['ok' => false, 'message' => 'ffprobe was not found on the web server\'s PATH (confirmed live), even if it\'s on the CLI\'s PATH - FPM pools often set their own, narrower PATH. Add ffprobe\'s directory to the FPM pool\'s env[PATH].'];
        }

        if (empty($live['timeoutFound'])) {
            return ['ok' => false, 'message' => 'timeout(1) was not found on the web server\'s PATH (confirmed live) - every ffmpeg/ffprobe run is wrapped in it to enforce a wall-clock limit on untrusted media. It ships with coreutils; add its directory to the FPM pool\'s env[PATH].'];
        }

        if (empty($live['bashFound'])) {
            return ['ok' => false, 'message' => 'bash was not found on the web server\'s PATH (confirmed live) - each transcode runs under a bash ulimit preamble for CPU/memory caps. Add bash\'s directory to the FPM pool\'s env[PATH].'];
        }

        return ['ok' => true, 'message' => 'exec()/shell_exec() available; ffmpeg, ffprobe, timeout and bash found (confirmed live via the web server)'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkTempDirectory(): array
    {
        if (PHP_SAPI !== 'cli') {
            if (!is_writable(sys_get_temp_dir())) {
                return ['ok' => false, 'message' => 'The system temp directory (' . sys_get_temp_dir() . ') is not writable - file uploads and link preview images stage through it.'];
            }

            return ['ok' => true, 'message' => 'temp directory writable (' . sys_get_temp_dir() . ')'];
        }

        // CLI: TMPDIR/sys_get_temp_dir() can resolve differently per FPM pool
        // env config. Confirm live.
        $live = self::liveFacts();

        if ($live === null || !isset($live['tempDir'])) {
            return ['ok' => true, 'message' => 'CLI temp directory (' . sys_get_temp_dir() . ') writable, but could not confirm the web server\'s live - check via the web setup wizard directly.'];
        }

        if (empty($live['tempDirWritable'])) {
            return ['ok' => false, 'message' => 'The web server\'s temp directory (' . $live['tempDir'] . ', confirmed live) is not writable, even though the CLI\'s (' . sys_get_temp_dir() . ') is fine - file uploads and link preview images stage through it.'];
        }

        return ['ok' => true, 'message' => 'temp directory writable (' . $live['tempDir'] . ', confirmed live via the web server)'];
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
                return ['ok' => false, 'message' => realpath($dir) . ' is not writable by this user (' . get_current_user() . '). It (and everything under it) must be writable by the web server user too - uploads are processed and stored there.'];
            }
        }

        if (PHP_SAPI !== 'cli') {
            return ['ok' => true, 'message' => 'upload directories exist and are writable'];
        }

        // CLI: writable by the CLI user doesn't prove the web server's own
        // user can write there too - they're very commonly different Unix
        // accounts (whoever ran this script vs. "apache"/"www-data"/etc.).
        // Confirm live.
        $live = self::liveFacts();

        if ($live === null || !is_array($live['uploadDirsWritable'] ?? null)) {
            return ['ok' => true, 'message' => 'upload directories exist and are writable by the CLI user (' . get_current_user() . '), but could not confirm the web server\'s own user live - check via the web setup wizard directly, since it commonly runs as a different Unix user.'];
        }

        $not_writable = [];

        foreach ($live['uploadDirsWritable'] as $relative_path => $writable) {
            if (!$writable) {
                $not_writable[] = $relative_path;
            }
        }

        if ($not_writable !== []) {
            return ['ok' => false, 'message' => implode(', ', $not_writable) . ' (confirmed live) - not writable by the web server\'s own user, even though the CLI user (' . get_current_user() . ') can write there. They\'re commonly different accounts - make the whole uploads/ tree writable by the web server\'s user.'];
        }

        return ['ok' => true, 'message' => 'upload directories exist and are writable (confirmed live via the web server)'];
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

    /**
     * A separate concern from checkWebSocketServer(): that check proves the
     * daemon *works right now* - it says nothing about whether it'll still be
     * running after a restart or reboot. Someone could start it manually
     * (`php bin/websocket-server.php &`) and satisfy that check while nothing
     * guarantees it comes back. Same idea as checkBackupTimerPersistence().
     * CLI-only for the same reason - systemd --user is tied to the Unix user
     * running this script, not a PHP SAPI concept.
     *
     * @return array{ok: bool, message: string}
     */
    private static function checkWebSocketServicePersistence(): array
    {
        if (PHP_SAPI !== 'cli') {
            return ['ok' => true, 'message' => 'not applicable under the web SAPI'];
        }

        if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
            return ['ok' => true, 'message' => 'systemctl not found - assuming a non-systemd process manager is set up separately'];
        }

        $service_status = trim((string) shell_exec('systemctl --user is-enabled glommer-websocket.service 2>/dev/null'));

        if ($service_status !== 'enabled') {
            return ['ok' => false, 'message' => 'glommer-websocket.service is not enabled - the daemon may be reachable right now, but nothing guarantees it survives a restart or reboot. See README.md\'s "Running the WebSocket server" section.'];
        }

        $user = trim((string) shell_exec('id -un 2>/dev/null')) ?: (get_current_user() ?: (string) getenv('USER'));
        $linger_status = strtolower(trim((string) shell_exec('loginctl show-user ' . escapeshellarg($user) . ' --property=Linger 2>/dev/null')));

        if (!str_contains($linger_status, 'yes')) {
            return ['ok' => false, 'message' => 'glommer-websocket.service is enabled, but lingering is not enabled for ' . $user . ' - the daemon stops as soon as this user logs out, and won\'t survive a reboot. Run: sudo loginctl enable-linger ' . $user];
        }

        return ['ok' => true, 'message' => 'glommer-websocket.service is enabled, lingering is on for ' . $user];
    }
}
