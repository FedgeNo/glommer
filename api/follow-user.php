<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
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
    JSONResponse::localizedError('thatIsNotAFediverseAccount', 404) -> send();
}

if ($target_user -> banned) {
    JSONResponse::localizedError('userNotFound', 404) -> send();
}

if (Block::exists((int) $current_user -> userId, $target_user_id)) {
    JSONResponse::localizedError('unableToFollowThatAccount', 403) -> send();
}

$rate_key = 'follow-user:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 60, 3600)) {
    JSONResponse::localizedError('tooManyFollowsPleaseWaitABitAndTryAgain', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

if (!ActivityPubKeys::isConfigured()) {
    JSONResponse::localizedError('fediverseSupportIsNotSetUpOnThisServerYet', 503) -> send();
}

// The account is already known - it only has a shadow row because someone
// resolved it before - so this re-follows by actor URI rather than going back
// through handle resolution.
$result = RemoteFollow::createForActor((int) $current_user -> userId, $target_user -> remoteActorURI);

if (!$result) {
    JSONResponse::localizedError('couldNotDeliverTheFollowRequestToThatServer', 502) -> send();
}

Friendship::addFollow((int) $current_user -> userId, $target_user_id);

JSONResponse::success(['following' => true]) -> send();
