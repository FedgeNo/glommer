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

$pending_status = 'pending';

$check_stmt = mysqli_prepare($mysqli, '
SELECT 1
    FROM `Friendships`
    WHERE `requesterId` = ? AND `addresseeId` = ? AND `status` = ?
');
mysqli_stmt_bind_param($check_stmt, 'iis', $current_user -> userId, $target_user_id, $pending_status);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) > 0) {
    $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Friendships`
    WHERE `requesterId` = ? AND `addresseeId` = ? AND `status` = ?
');
    mysqli_stmt_bind_param($delete_stmt, 'iis', $current_user -> userId, $target_user_id, $pending_status);
    mysqli_stmt_execute($delete_stmt);
    $sent = false;
} else {
    if (Block::exists($current_user -> userId, $target_user_id)) {
        JSONResponse::error('Unable to send friend request.', 403) -> send();
    }

    try {
        $insert_stmt = mysqli_prepare($mysqli, '
INSERT INTO `Friendships` (`requesterId`, `addresseeId`)
    VALUES (?, ?)
');
        mysqli_stmt_bind_param($insert_stmt, 'ii', $current_user -> userId, $target_user_id);
        mysqli_stmt_execute($insert_stmt);
    } catch (\mysqli_sql_exception $e) {
        JSONResponse::error('A friend request already exists between you and that user.', 422) -> send();
    }

    $sent = true;

    Notification::create($target_user_id, $current_user -> userId, 'friendRequest');
}

JSONResponse::success(['sent' => $sent]) -> send();
