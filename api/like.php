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

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$post_id = (int) ($payload['itemId'] ?? $_POST['itemId'] ?? 0);

$owner = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

if ($owner === null) {
    JSONResponse::error('Post not found', 404) -> send();
}

if (Block::exists($current_user -> userId, (int) $owner -> userId)) {
    JSONResponse::error('Unable to like this post', 403) -> send();
}

$check_stmt = DB::run('
SELECT 1
    FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
', 'ii', $post_id, $current_user -> userId);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) > 0) {
    DB::run('
DELETE
    FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
', 'ii', $post_id, $current_user -> userId);
    $liked = false;
} else {
    $insert_stmt = DB::prepare('
INSERT INTO `Likes` (`postId`, `userId`)
    VALUES (?, ?)
');
    DB::bind($insert_stmt, 'ii', $post_id, $current_user -> userId);
    $liked = true;

    try {
        mysqli_stmt_execute($insert_stmt);

        Notification::create((int) $owner -> userId, $current_user -> userId, 'like', $post_id);
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

$count_stmt = DB::run('
SELECT COUNT(*) AS `likeCount`
    FROM `Likes`
    WHERE `postId` = ?
', 'i', $post_id);
$count_result = mysqli_stmt_get_result($count_stmt);
$count = (int) mysqli_fetch_assoc($count_result)['likeCount'];

JSONResponse::success(['liked' => $liked, 'count' => $count]) -> send();
