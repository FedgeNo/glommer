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
$pending_status = 'pending';

// Only a still-pending request can be denied. Without the status guard a real
// accepted friendshipId (exposed to the client) could delete a friendship here
// without going through removeAccepted, leaving both friendCounts inflated and
// timeline entries orphaned.
$stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Friendships`
    WHERE `friendshipId` = ? AND `addresseeId` = ? AND `status` = ?
');
mysqli_stmt_bind_param($stmt, 'iis', $friendship_id, $current_user -> userId, $pending_status);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) === 0) {
    JSONResponse::error('Not your request', 403) -> send();
}

JSONResponse::success(['denied' => true]) -> send();
