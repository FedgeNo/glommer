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
$accepted_status = 'accepted';

$stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Friendships`
    WHERE `status` = ? AND ((`requesterId` = ? AND `addresseeId` = ?) OR (`requesterId` = ? AND `addresseeId` = ?))
');
mysqli_stmt_bind_param($stmt, 'siiii', $accepted_status, $current_user -> userId, $target_user_id, $target_user_id, $current_user -> userId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) === 0) {
    JSONResponse::error('Not friends with that user', 404) -> send();
}

JSONResponse::success(['removed' => true]) -> send();
