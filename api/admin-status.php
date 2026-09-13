<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

Auth::requireLogin();

if (Auth::id() !== 1) {
    JSONResponse::localizedError('forbidden', 403) -> send();
}

$data = APIRequest::read(['overview' => 'boolean']);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

JSONResponse::success(AdminStatus::snapshot(($data['overview'] ?? false) === true)) -> send();
