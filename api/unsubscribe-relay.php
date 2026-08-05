<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

// The primary admin only, same as subscribing.
if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$actor_uri = trim((string) ($payload['actorURI'] ?? ''));

if ($actor_uri === '') {
    JSONResponse::error('Invalid request', 422) -> send();
}

if (!Relay::unsubscribe($actor_uri)) {
    JSONResponse::error('Not subscribed to that relay.', 404) -> send();
}

JSONResponse::success(['unsubscribed' => true]) -> send();
