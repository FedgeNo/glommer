<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$friendship_id = (int) ($payload['friendshipId'] ?? $_POST['friendshipId'] ?? 0);

$accepted_status = 'accepted';
$pending_status = 'pending';

// Look the request up first: we need the requester both to enforce the friend
// cap on them and to notify them, and doing it up front lets us reject a
// capped acceptance before changing anything.
$lookup_stmt = mysqli_prepare($mysqli, '
SELECT `requesterId`
    FROM `Friendships`
    WHERE `friendshipId` = ? AND `addresseeId` = ? AND `status` = ?
');
mysqli_stmt_bind_param($lookup_stmt, 'iis', $friendship_id, $current_user -> userId, $pending_status);
mysqli_stmt_execute($lookup_stmt);
$requester_row = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup_stmt));

if ($requester_row === null) {
    JSONResponse::error('Not your request', 403) -> send();
}

$requester_id = (int) $requester_row['requesterId'];

// Accepting makes both people a friend of the other, so both must be under
// the cap - the requester may have filled up since sending this.
if (User::atFriendCap($current_user -> userId)) {
    JSONResponse::error('You\'ve reached the maximum of ' . User::MAX_FRIENDS . ' friends.', 422) -> send();
}

if (User::atFriendCap($requester_id)) {
    JSONResponse::error('That user has reached their friend limit, so this request can\'t be accepted.', 422) -> send();
}

$stmt = mysqli_prepare($mysqli, '
UPDATE `Friendships`
    SET `status` = ?
    WHERE `friendshipId` = ? AND `addresseeId` = ? AND `status` = ?
');
mysqli_stmt_bind_param($stmt, 'siis', $accepted_status, $friendship_id, $current_user -> userId, $pending_status);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) === 0) {
    JSONResponse::error('Not your request', 403) -> send();
}

User::incrementFriendCounts($requester_id, (int) $current_user -> userId);

Timeline::backfillFriendship($requester_id, $current_user -> userId);

Notification::create($requester_id, $current_user -> userId, 'friendAccepted');

JSONResponse::success(['accepted' => true]) -> send();
