<?php

declare(strict_types=1);

/**
 * Uptime-monitoring endpoint at /health: exercises PHP, a real database round
 * trip, and the two always-on background services the site depends on for its
 * core functionality (live notifications/messages over WebSocket, and media
 * processing via the upload worker) - a confirmed outage in any of them is as
 * real a problem as the site itself being down, so it flips this endpoint the
 * same way a DB failure does; nothing here is "optional extra" enough to stay
 * quietly broken. Deliberately does NOT go through src/init.php - init's
 * connection failure path renders the setup/maintenance page with a 200,
 * which would make a monitor see a dead database as healthy. No details are
 * exposed either way - just healthy or not.
 */

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$respond = static function (bool $healthy): void {
    if ($healthy) {
        JSONResponse::success(['ok' => true]) -> send();
    }

    JSONResponse::error('unhealthy', 503) -> send();
};

// This endpoint is unauthenticated and the checks below are relatively
// expensive (systemctl shell-outs plus a real WebSocket handshake), so a
// fresh result is cached briefly rather than recomputed on every hit - which
// would otherwise be a cheap way to load the box. A monitor polling faster
// than the TTL is served the cached verdict.
$cache_file = sys_get_temp_dir() . '/glommer-health.json';
$cache_ttl_seconds = 60;

if (is_file($cache_file) && (time() - (int) filemtime($cache_file)) < $cache_ttl_seconds) {
    $cached = json_decode((string) @file_get_contents($cache_file), true);

    if (is_array($cached) && isset($cached['healthy'])) {
        $respond((bool) $cached['healthy']);
    }
}

$healthy = true;

// A dead database is the definitive unhealthy signal. Deliberately NOT via
// src/init.php - init's connection-failure path renders the maintenance page
// with a 200, which would read as healthy.
try {
    mysqli_query(DB::connection(), '
SELECT 1
');
} catch (\Throwable $exception) {
    $healthy = false;
}

// The two always-on background services. workerIsActive() returning null
// (can't tell - systemctl unavailable, or SELinux denying the web server's
// own status query) is NOT a failure; only a confirmed "not running" is. A
// throw here can't be read as "confirmed down" either, so it never flips the
// verdict by itself - and catching it keeps an unexpected error from escaping
// as a raw 500 instead of a JSON health response.
if ($healthy) {
    try {
        $websocket_ok = EnvironmentChecker::checkWebSocketServer()['ok'];
        $upload_worker_active = UploadBatch::workerIsActive();

        if (!$websocket_ok || $upload_worker_active === false) {
            $healthy = false;
        }
    } catch (\Throwable $exception) {
        // Indeterminate - leave the verdict as the database probe found it.
    }
}

@file_put_contents($cache_file, json_encode(['healthy' => $healthy]), LOCK_EX);

$respond($healthy);
