<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

// The primary admin only, same as subscribing.
if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

$payload = APIRequest::read(['actorURI' => 'text']);
$actor_uri = trim((string) ($payload['actorURI'] ?? ''));

if ($actor_uri === '') {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
}

if (!Relay::unsubscribe($actor_uri)) {
    JSONResponse::localizedError('notSubscribedToThatRelay', 404) -> send();
}

JSONResponse::success(['unsubscribed' => true]) -> send();
