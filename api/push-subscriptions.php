<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}
Auth::requireLogin();
$payload = json_decode((string) file_get_contents('php://input'), true);
$endpoint = is_array($payload) && is_string($payload['endpoint'] ?? null) ? $payload['endpoint'] : '';
JSONResponse::success(PushSubscription::status((int) Auth::id(), $endpoint)) -> send();
