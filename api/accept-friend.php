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

$requester_stmt = mysqli_prepare($mysqli, '
SELECT `requesterId`
    FROM `Friendships`
    WHERE `friendshipId` = ?
');
mysqli_stmt_bind_param($requester_stmt, 'i', $friendship_id);
mysqli_stmt_execute($requester_stmt);
$requester_result = mysqli_stmt_get_result($requester_stmt);
$requester_row = mysqli_fetch_assoc($requester_result);

Timeline::backfillFriendship((int) $requester_row['requesterId'], $current_user -> userId);

Notification::create((int) $requester_row['requesterId'], $current_user -> userId, 'friendAccepted');

JSONResponse::success(['accepted' => true]) -> send();
