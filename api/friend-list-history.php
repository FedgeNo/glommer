<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// Paced per client, like every other list a signed-out visitor can page. A page
// here loads a row of accounts and builds each one's card, so it costs more
// than the single indexed read it looks like, and the offset is the caller's -
// which is what turns a loop into a walk through the whole table.
$rate_key = 'friend-list-history:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
    JSONResponse::localizedError('tooManyRequestsPleaseSlowDown', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

// Serves the next page for any of the three friend sections. The friends list
// is public (anyone can browse a profile's friends); the incoming/outgoing
// request lists are private to their owner, so those require you to be the
// person whose page it is.
$list_type = (string) ($payload['listType'] ?? '');
$user_id = (int) ($payload['userId'] ?? 0);
// How many cards the client's section already shows - the next page starts
// there.
$offset = max(0, (int) ($payload['offset'] ?? 0));

if (!in_array($list_type, ['friends', 'incoming', 'outgoing'], true) || $user_id === 0) {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
}

if (($list_type === 'incoming' || $list_type === 'outgoing') && Auth::id() !== $user_id) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

$profile_user = User::load($user_id);

if ($profile_user === null || $profile_user -> banned) {
    JSONResponse::localizedError('userNotFound', 404) -> send();
}

$viewer = Auth::user();

// The three lists own their queries; the endpoint just constructs the right
// one for the next page and serializes what it fetched.
$list = match ($list_type) {
    'incoming' => new ReceivedFriendRequestList(['user' => $profile_user, 'offset' => $offset]),
    'outgoing' => new SentFriendRequestList(['user' => $profile_user, 'offset' => $offset]),
    default => new FriendList(['user' => $profile_user, 'offset' => $offset]),
};

$page = $list -> toJSON();

$payloads = [];

foreach ($page['items'] as $item) {
    $item_payload = OtherUser::payloadFor($item, $viewer);

    // Incoming requests carry the friendshipId so the client can render the
    // Accept/Deny buttons, which act on that Friendships row.
    if ($list_type === 'incoming') {
        $item_payload['friendshipId'] = (int) $item -> friendshipId;
    }

    $payloads[] = $item_payload;
}

JSONResponse::success([
    'items' => $payloads,
    'hasMore' => $page['hasMore'],
]) -> send();
