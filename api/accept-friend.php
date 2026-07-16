<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = DB::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$friendship_id = (int) ($payload['friendshipId'] ?? $_POST['friendshipId'] ?? 0);

$accepted_status = 'accepted';
$pending_status = 'pending';
$max_friends = User::MAX_FRIENDS;
$current_user_id = (int) $current_user -> userId;

// The cap check and the two friendCount increments must be atomic, or two
// requests accepted at the same moment could both read "under cap" and push a
// user to 5001. Do it all in one transaction, locking the request row and both
// user rows so concurrent accepts serialize.
mysqli_begin_transaction($mysqli);

$lookup_stmt = mysqli_prepare($mysqli, '
SELECT `requesterId`
    FROM `Friendships`
    WHERE `friendshipId` = ? AND `addresseeId` = ? AND `status` = ?
    FOR UPDATE
');
mysqli_stmt_bind_param($lookup_stmt, 'iis', $friendship_id, $current_user_id, $pending_status);
mysqli_stmt_execute($lookup_stmt);
$requester_row = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup_stmt));

if ($requester_row === null) {
    mysqli_rollback($mysqli);
    JSONResponse::error('Not your request', 403) -> send();
}

$requester_id = (int) $requester_row['requesterId'];

// Lock both users' rows and read their counts under the lock - no TOCTOU.
$counts_stmt = mysqli_prepare($mysqli, '
SELECT `userId`, `friendCount`
    FROM `Users`
    WHERE `userId` = ? OR `userId` = ?
    FOR UPDATE
');
mysqli_stmt_bind_param($counts_stmt, 'ii', $current_user_id, $requester_id);
mysqli_stmt_execute($counts_stmt);
$counts_result = mysqli_stmt_get_result($counts_stmt);

$friend_counts = [];

while ($count_row = mysqli_fetch_assoc($counts_result)) {
    $friend_counts[(int) $count_row['userId']] = (int) $count_row['friendCount'];
}

if (($friend_counts[$current_user_id] ?? 0) >= $max_friends) {
    mysqli_rollback($mysqli);
    JSONResponse::error('You\'ve reached the maximum of ' . $max_friends . ' friends.', 422) -> send();
}

if (($friend_counts[$requester_id] ?? 0) >= $max_friends) {
    mysqli_rollback($mysqli);
    JSONResponse::error('That user has reached their friend limit, so this request can\'t be accepted.', 422) -> send();
}

$accept_stmt = mysqli_prepare($mysqli, '
UPDATE `Friendships`
    SET `status` = ?
    WHERE `friendshipId` = ? AND `addresseeId` = ? AND `status` = ?
');
mysqli_stmt_bind_param($accept_stmt, 'siis', $accepted_status, $friendship_id, $current_user_id, $pending_status);
mysqli_stmt_execute($accept_stmt);

if (mysqli_stmt_affected_rows($accept_stmt) === 0) {
    mysqli_rollback($mysqli);
    JSONResponse::error('Not your request', 403) -> send();
}

$increment_stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `friendCount` = `friendCount` + 1
    WHERE `userId` = ? OR `userId` = ?
');
mysqli_stmt_bind_param($increment_stmt, 'ii', $current_user_id, $requester_id);
mysqli_stmt_execute($increment_stmt);

mysqli_commit($mysqli);

// Side effects that don't need to be in the transaction.
Timeline::backfillFriendship($requester_id, $current_user_id);

Notification::create($requester_id, $current_user_id, 'friendAccepted');

// Return the requester's refreshed OtherUser payload (as seen by the accepter)
// so the client rebuilds the card from the same class the page renders
// everywhere else - it now carries friendshipStatus 'accepted', hence a Remove
// Friend action - rather than hand-assembling a partial card.
$requester = User::load($requester_id);

if ($requester === null) {
    JSONResponse::success(['accepted' => true]) -> send();
}

JSONResponse::success(OtherUser::payloadFor($requester, $current_user)) -> send();
