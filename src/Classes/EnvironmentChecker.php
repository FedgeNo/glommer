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
    /**
     * @return array<string, array{ok: bool, message: string}>
     */
    public static function checks(): array
    {
        return [
            'PHP version' => self::checkPhpVersion(),
            'PHP extensions' => self::checkExtensions(),
            'Shell execution & media binaries' => self::checkShellAndFfmpeg(),
            'Temp directory' => self::checkTempDirectory(),
            'Upload directories' => self::checkUploadDirectories(),
            'SELinux' => self::checkSELinux(),
            'Outbound network' => self::checkOutboundNetwork(),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private static function checkPhpVersion(): array
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
        if (SafeHttpFetcher::get('https://example.com/', 65536) === null) {
            return ['ok' => false, 'message' => 'An outbound HTTPS request failed - link previews need internet access. Common causes: a firewall blocking egress, no DNS resolution for the PHP process, or a container with no network.'];
        }

        return ['ok' => true, 'message' => 'outbound HTTPS requests work'];
    }
}
