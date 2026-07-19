<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$target_user_id = (int) ($payload['userId'] ?? 0);

$target_user = User::load($target_user_id);

// Following is one-way and only exists for Fediverse accounts; between two
// local accounts the relationship is a friendship, which is mutual and has
// its own endpoint.
if ($target_user === null || $target_user -> remoteActorURI === null) {
    JSONResponse::error('That is not a Fediverse account.', 404) -> send();
}

if ($target_user -> banned) {
    JSONResponse::error('User not found', 404) -> send();
}

if (Block::exists((int) $current_user -> userId, $target_user_id)) {
    JSONResponse::error('Unable to follow that account.', 403) -> send();
}

$rate_key = 'follow-user:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 60, 3600)) {
    JSONResponse::error('Too many follows. Please wait a bit and try again.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

if (!ActivityPubKeys::isConfigured()) {
    JSONResponse::error('Fediverse support is not set up on this server yet.', 503) -> send();
}

// The account is already known - it only has a shadow row because someone
// resolved it before - so this re-follows by actor URI rather than going back
// through handle resolution.
$result = RemoteFollow::createForActor((int) $current_user -> userId, $target_user -> remoteActorURI);

if (!$result) {
    JSONResponse::error('Could not deliver the follow request to that server.', 502) -> send();
}

Friendship::addFollow((int) $current_user -> userId, $target_user_id);

JSONResponse::success(['following' => true]) -> send();
