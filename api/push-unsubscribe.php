<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

Auth::requireLogin();

$payload = APIRequest::read(['endpoint' => 'text', 'subscriptionId' => 'integer']);

$payload = is_array($payload) ? $payload : [];
$endpoint = is_string($payload['endpoint'] ?? null) ? trim($payload['endpoint']) : '';
$id = is_int($payload['subscriptionId'] ?? null) ? $payload['subscriptionId'] : null;
PushSubscription::remove((int) Auth::id(), $id, $endpoint);
JSONResponse::success(['unsubscribed' => true] + PushSubscription::status((int) Auth::id(), $endpoint)) -> send();
