<?php

declare(strict_types=1);

/**
 * Internal-use endpoint for bin/install.php's live environment checks:
 * reports every fact PHP actually resolves under the web SAPI that the CLI
 * SAPI can't reliably speak for - ini values .user.ini/disable_functions
 * only apply to FPM/CGI, PATH and the writing Unix user an FPM pool commonly
 * sets independently, loaded extensions that can differ on split-package
 * distros. Raw facts only, no pass/fail judgment - EnvironmentChecker (which
 * already has that logic for the CLI-native case) applies the same
 * thresholds to whichever source (ini_get() directly, or this) is live.
 * Deliberately DB-independent (must work even pre-install, before .env
 * exists) and exposes nothing sensitive - none of this is secret.
 */

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Every legitimate caller (EnvironmentChecker::liveFacts()) reaches this over
// loopback, by design - it's how bin/install.php and the setup wizard probe
// the web SAPI's own view of the environment. Nothing here is secret, but
// there's no reason for it to answer a request that didn't come from this
// machine either.
//
// REMOTE_ADDR alone can't tell that apart: this app runs behind a
// TLS-terminating reverse proxy (see ServerURL::isHTTPS()), which forwards
// every request - including from real external visitors - to Apache over
// loopback. So REMOTE_ADDR is 127.0.0.1 for EVERYONE, not just a direct
// probe. The proxy adds X-Forwarded-For to whatever it forwards; a genuine
// direct loopback call (curl straight to http://127.0.0.1/environment-check,
// bypassing the proxy entirely) never has that header at all. Requiring both
// is what actually restricts this to a direct local call.
if (
    !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
) {
    JSONResponse::error('Not found', 404) -> send();
}

$disabled_functions = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$exec_disabled = in_array('exec', $disabled_functions, true);
$shell_exec_disabled = in_array('shell_exec', $disabled_functions, true);

$ffmpeg_found = false;
$ffprobe_found = false;
$timeout_found = false;
$bash_found = false;

// Only actually call shell_exec() if it's genuinely available - calling a
// disabled function still just emits a warning rather than fataling, but
// there's no reason to even try.
if (function_exists('shell_exec') && !$shell_exec_disabled) {
    $ffmpeg_found = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null')) !== '';
    $ffprobe_found = trim((string) @shell_exec('command -v ffprobe 2>/dev/null')) !== '';
    // timeout(1) (coreutils) and bash wrap every transcode with wall-clock /
    // CPU / memory limits (UploadProcessor::guardedCommand), so they're as
    // load-bearing for the media pipeline as ffmpeg itself.
    $timeout_found = trim((string) @shell_exec('command -v timeout 2>/dev/null')) !== '';
    $bash_found = trim((string) @shell_exec('command -v bash 2>/dev/null')) !== '';
}

// The Unix uid the web server actually runs as. get_current_user() returns the
// SCRIPT's owner, not the process user, so instead create a file and read its
// owner - a file is owned by the effective uid of the process that made it, so
// this is the real web-server uid. The CLI installer (running as root under
// sudo) maps it to a name to chown uploads/ correctly.
$web_server_uid = null;
$uid_probe = sys_get_temp_dir() . '/glommer-uid-probe-' . bin2hex(random_bytes(6));

if (@file_put_contents($uid_probe, '') !== false) {
    $probe_owner = @fileowner($uid_probe);

    if ($probe_owner !== false) {
        $web_server_uid = $probe_owner;
    }

    @unlink($uid_probe);
}

$upload_dirs = [
    'uploads' => __DIR__ . '/uploads',
    'uploads/avatars' => __DIR__ . '/uploads/avatars',
    'uploads/private' => __DIR__ . '/uploads/private',
    'uploads/private/originals' => __DIR__ . '/uploads/private/originals',
    'uploads/private/staging' => __DIR__ . '/uploads/private/staging',
    'uploads/private/pending' => __DIR__ . '/uploads/private/pending',
    'uploads/private/processing' => __DIR__ . '/uploads/private/processing',
];

$upload_dirs_writable = [];

foreach ($upload_dirs as $label => $path) {
    $upload_dirs_writable[$label] = is_dir($path) && is_writable($path);
}

JSONResponse::success([
    'phpVersion' => PHP_VERSION,
    'uploadMaxFilesize' => ini_get('upload_max_filesize'),
    'postMaxSize' => ini_get('post_max_size'),
    'maxFileUploads' => ini_get('max_file_uploads'),
    'execFunctionExists' => function_exists('exec'),
    'execDisabled' => $exec_disabled,
    'shellExecFunctionExists' => function_exists('shell_exec'),
    'shellExecDisabled' => $shell_exec_disabled,
    'ffmpegFound' => $ffmpeg_found,
    'ffprobeFound' => $ffprobe_found,
    'timeoutFound' => $timeout_found,
    'bashFound' => $bash_found,
    'webServerUid' => $web_server_uid,
    // Apache sets this itself; nginx (fronting PHP-FPM) passes it through via
    // fastcgi_params. A genuine live signal of what's actually serving this
    // request - stronger evidence than a process-name check (pgrep just
    // shows something with that name is running, not that it's the one
    // handling traffic, and says nothing when a reverse proxy fronts it).
    'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? null,
    'extensions' => [
        'mysqli' => extension_loaded('mysqli'),
        'gd' => extension_loaded('gd'),
        'curl' => extension_loaded('curl'),
        'dom' => extension_loaded('dom'),
        'libxml' => extension_loaded('libxml'),
        'fileinfo' => extension_loaded('fileinfo'),
        'mbstring' => extension_loaded('mbstring'),
    ],
    'tempDir' => sys_get_temp_dir(),
    'tempDirWritable' => is_writable(sys_get_temp_dir()),
    'currentUser' => get_current_user(),
    'uploadDirsWritable' => $upload_dirs_writable,
]) -> send();
