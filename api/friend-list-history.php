<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Serves the next page for any of the three friend sections. The friends list
// is public (anyone can browse a profile's friends); the incoming/outgoing
// request lists are private to their owner, so those require you to be the
// person whose page it is.
$list_type = (string) ($_GET['listType'] ?? '');
$user_id = (int) ($_GET['userId'] ?? 0);
$before_friendship_id = (int) ($_GET['beforeFriendshipId'] ?? 0);

if (!in_array($list_type, ['friends', 'incoming', 'outgoing'], true) || $user_id === 0 || $before_friendship_id === 0) {
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
$limit = 20;
$fetch_limit = $limit + 1;

$items = match ($list_type) {
    'incoming' => $profile_user -> getIncomingFriendRequests($fetch_limit, $before_friendship_id),
    'outgoing' => $profile_user -> getOutgoingFriendRequests($fetch_limit, $before_friendship_id),
    default => $profile_user -> getFriends($fetch_limit, $before_friendship_id),
};

$has_more = count($items) > $limit;

if ($has_more) {
    array_pop($items);
}

$oldest_friendship_id = $items !== [] ? (int) end($items) -> friendshipId : null;

$payloads = [];

foreach ($items as $item) {
    $payload = OtherUser::payloadFor($item, $viewer);

    // Incoming requests carry the friendshipId so the client can render the
    // Accept/Deny buttons, which act on that Friendships row.
    if ($list_type === 'incoming') {
        $payload['friendshipId'] = (int) $item -> friendshipId;
    }

    $payloads[] = $payload;
}

JSONResponse::success([
    'items' => $payloads,
    'hasMore' => $has_more,
    'oldestFriendshipId' => $oldest_friendship_id,
]) -> send();
