<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

$current_user = Auth::user();

// A request can be cancelled and sent again, so it is paced like the other
// toggles (the notification itself is deduplicated).
$friend_request_rate_key = 'friend-request:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($friend_request_rate_key, 60, 600)) {
    JSONResponse::localizedError('youReDoingThatVeryQuicklyPleaseWaitAMoment', 429) -> send();
}

RateLimiter::recordAttempt($friend_request_rate_key);

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$target_user_id = (int) ($payload['userId'] ?? 0);

if ($target_user_id === $current_user -> userId) {
    JSONResponse::localizedError('youCanTSendAFriendRequestToYourself', 422) -> send();
}

$target_user = User::load($target_user_id);

if ($target_user === null || $target_user -> banned) {
    JSONResponse::localizedError('userNotFound', 404) -> send();
}

// Friendship is mutual and needs the other side to accept; a Fediverse
// account can only be followed, which is the one-way link api/follow-user.php
// creates.
if ($target_user -> remoteActorURI !== null) {
    JSONResponse::localizedError('thatIsAFediverseAccountFollowItInstead', 422) -> send();
}

// Look at the relationship in BOTH directions - the Friendships unique key is
// on the ordered (requesterId, addresseeId) pair, so it can't stop a duplicate
// reverse-direction row on its own. statusBetween is the guard against creating
// a second row for a relationship that already exists.
$existing = Friendship::statusBetween((int) $current_user -> userId, $target_user_id);

if ($existing !== null) {
    $sent_by_me = (int) $existing -> requesterId === (int) $current_user -> userId;

    // The request I already sent, tapped again -> cancel it.
    if ($existing -> status === 'pending' && $sent_by_me) {
        DB::run('
DELETE
    FROM `Friendships`
    WHERE `friendshipId` = ?
', 'i', $existing -> friendshipId);

        JSONResponse::success(['sent' => false]) -> send();
    }

    if ($existing -> status === 'accepted') {
        JSONResponse::localizedError('youReAlreadyFriendsWithThatUser', 422) -> send();
    }

    // A pending request the other way round - they asked first.
    JSONResponse::localizedError('thatUserHasAlreadySentYouAFriendRequestAcceptItInstead', 422) -> send();
}

if (Block::exists($current_user -> userId, $target_user_id)) {
    JSONResponse::localizedError('unableToSendFriendRequest', 403) -> send();
}

// A user at the friend cap can neither send requests nor receive them.
if (User::atFriendCap($current_user -> userId)) {
    JSONResponse::localizedError('maximumFriends', 422, ['count' => User::MAX_FRIENDS]) -> send();
}

if (User::atFriendCap($target_user_id)) {
    JSONResponse::localizedError('thatUserHasReachedTheirFriendLimit', 422) -> send();
}

try {
    DB::run('
INSERT INTO `Friendships` (`requesterId`, `addresseeId`)
    VALUES (?, ?)
', 'ii', $current_user -> userId, $target_user_id);
} catch (\mysqli_sql_exception $exception) {
    // The statusBetween() check above has a TOCTOU gap: two simultaneous
    // reverse-direction requests (this one, and the same target sending one
    // back) can both pass it, since neither row exists yet at the moment
    // either checks. uniq_unordered_pair (on the pair sorted low/high,
    // unlike uniq_pair which is ordered and can't catch this) is the actual
    // guard - only one of the two INSERTs can win. 1062 is MySQL's duplicate-
    // key error; anything else is a real failure, not a race.
    if ($exception -> getCode() !== 1062) {
        throw $exception;
    }

    JSONResponse::localizedError('aFriendRequestOrFriendshipAlreadyExistsWithThatUser', 422) -> send();
}

Notification::create($target_user_id, $current_user -> userId, 'friendRequest');

JSONResponse::success(['sent' => true]) -> send();
