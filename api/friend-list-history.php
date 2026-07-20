<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

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
    JSONResponse::error('Invalid request', 422) -> send();
}

if (($list_type === 'incoming' || $list_type === 'outgoing') && Auth::id() !== $user_id) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$profile_user = User::load($user_id);

if ($profile_user === null || $profile_user -> banned) {
    JSONResponse::error('User not found', 404) -> send();
}

$viewer = Auth::user();

// The three lists own their queries; the endpoint just constructs the right
// one for the next page and serializes what it fetched.
$list = match ($list_type) {
    'incoming' => new PendingFriendRequestList(['user' => $profile_user, 'offset' => $offset]),
    'outgoing' => new OutgoingFriendRequestList(['user' => $profile_user, 'offset' => $offset]),
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
