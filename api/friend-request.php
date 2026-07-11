<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$target_user_id = (int) ($payload['userId'] ?? $_POST['userId'] ?? 0);

if ($target_user_id === $current_user -> userId) {
    JSONResponse::error('You can\'t send a friend request to yourself.', 422) -> send();
}

$target_user = User::load($target_user_id);

if ($target_user === null || $target_user -> banned) {
    JSONResponse::error('User not found', 404) -> send();
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
        $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Friendships`
    WHERE `friendshipId` = ?
');
        mysqli_stmt_bind_param($delete_stmt, 'i', $existing -> friendshipId);
        mysqli_stmt_execute($delete_stmt);

        JSONResponse::success(['sent' => false]) -> send();
    }

    if ($existing -> status === 'accepted') {
        JSONResponse::error('You\'re already friends with that user.', 422) -> send();
    }

    // A pending request the other way round - they asked first.
    JSONResponse::error('That user has already sent you a friend request - accept it instead.', 422) -> send();
}

if (Block::exists($current_user -> userId, $target_user_id)) {
    JSONResponse::error('Unable to send friend request.', 403) -> send();
}

// A user at the friend cap can neither send requests nor receive them.
if (User::atFriendCap($current_user -> userId)) {
    JSONResponse::error('You\'ve reached the maximum of ' . User::MAX_FRIENDS . ' friends.', 422) -> send();
}

if (User::atFriendCap($target_user_id)) {
    JSONResponse::error('That user has reached their friend limit.', 422) -> send();
}

$insert_stmt = mysqli_prepare($mysqli, '
INSERT INTO `Friendships` (`requesterId`, `addresseeId`)
    VALUES (?, ?)
');
mysqli_stmt_bind_param($insert_stmt, 'ii', $current_user -> userId, $target_user_id);
mysqli_stmt_execute($insert_stmt);

Notification::create($target_user_id, $current_user -> userId, 'friendRequest');

JSONResponse::success(['sent' => true]) -> send();
