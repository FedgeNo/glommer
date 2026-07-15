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

try {
    mysqli_query(Database::connection(), '
SELECT 1
');
} catch (\Throwable $exception) {
    JSONResponse::error('unhealthy', 503) -> send();
}

// workerIsActive() returning null (can't tell - e.g. systemctl unavailable,
// or SELinux denying the web server's own status query) is NOT treated as a
// failure, only a definitively confirmed "not running" is - a host that
// merely can't be asked shouldn't false-page over it.
$websocket_ok = EnvironmentChecker::checkWebSocketServer()['ok'];
$upload_worker_active = UploadBatch::workerIsActive();

if (!$websocket_ok || $upload_worker_active === false) {
    JSONResponse::error('unhealthy', 503) -> send();
}

JSONResponse::success(['ok' => true]) -> send();
