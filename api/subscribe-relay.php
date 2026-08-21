<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

// The primary admin only: subscribing commits this server's storage and
// bandwidth to whatever the other side publishes.
if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$actor_uri = trim((string) ($payload['actorURI'] ?? ''));
$follow_object = (string) ($payload['followObject'] ?? Relay::FOLLOW_PUBLIC);

if (strlen($actor_uri) > 255) {
    JSONResponse::localizedError('thatAddressIsTooLong', 422) -> send();
}

// Subscribing fetches the relay's actor document, so it is paced: without
// this the field is a way to make the server issue outbound requests at
// whatever address is typed, as fast as they can be submitted.
$rate_key = 'subscribe-relay:' . Auth::id();

if (RateLimiter::tooManyAttempts($rate_key, 10, 600)) {
    JSONResponse::localizedError('tooManyAttemptsPleaseWaitAMoment', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

$problem = Relay::subscribe($actor_uri, $follow_object);

if ($problem !== null) {
    JSONResponse::error($problem, 422) -> send();
}

JSONResponse::success(['subscribed' => true, 'actorURI' => $actor_uri]) -> send();
