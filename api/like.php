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
$post_id = (int) ($payload['itemId'] ?? $_POST['itemId'] ?? 0);

$owner_stmt = mysqli_prepare($mysqli, '
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($owner_stmt, 'i', $post_id);
mysqli_stmt_execute($owner_stmt);
$owner_result = mysqli_stmt_get_result($owner_stmt);
$owner_row = mysqli_fetch_assoc($owner_result);

if ($owner_row === null) {
    JSONResponse::error('Post not found', 404) -> send();
}

if (Block::exists($current_user -> userId, (int) $owner_row['userId'])) {
    JSONResponse::error('Unable to like this post', 403) -> send();
}

$check_stmt = mysqli_prepare($mysqli, '
SELECT 1
    FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
');
mysqli_stmt_bind_param($check_stmt, 'ii', $post_id, $current_user -> userId);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) > 0) {
    $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
');
    mysqli_stmt_bind_param($delete_stmt, 'ii', $post_id, $current_user -> userId);
    mysqli_stmt_execute($delete_stmt);
    $liked = false;
} else {
    $insert_stmt = mysqli_prepare($mysqli, '
INSERT INTO `Likes` (`postId`, `userId`)
    VALUES (?, ?)
');
    mysqli_stmt_bind_param($insert_stmt, 'ii', $post_id, $current_user -> userId);
    $liked = true;

    try {
        mysqli_stmt_execute($insert_stmt);

        Notification::create((int) $owner_row['userId'], $current_user -> userId, 'like', $post_id);
    } catch (\mysqli_sql_exception $exception) {
        // Check-then-insert race (a double-submit or two concurrent requests
        // both passing the existence check): the Likes PK (userId, postId)
        // rejects the second INSERT. 1062 means the like already exists -
        // treat it as the already-liked state (no duplicate notification),
        // not a 500. Anything else is a real failure.
        if ($exception -> getCode() !== 1062) {
            throw $exception;
        }
    }
}

$count_stmt = mysqli_prepare($mysqli, '
SELECT COUNT(*) AS `likeCount`
    FROM `Likes`
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($count_stmt, 'i', $post_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$count = (int) mysqli_fetch_assoc($count_result)['likeCount'];

JSONResponse::success(['liked' => $liked, 'count' => $count]) -> send();
