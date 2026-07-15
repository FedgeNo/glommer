<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$post_id = (int) ($payload['itemId'] ?? $_POST['itemId'] ?? 0);

$owner_stmt = mysqli_prepare($mysqli, '
SELECT 1
    FROM `Posts`
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($owner_stmt, 'i', $post_id);
mysqli_stmt_execute($owner_stmt);
mysqli_stmt_store_result($owner_stmt);

if (mysqli_stmt_num_rows($owner_stmt) === 0) {
    JSONResponse::error('Post not found', 404) -> send();
}

$bookmarked = Bookmark::toggle($current_user -> userId, $post_id);

JSONResponse::success(['bookmarked' => $bookmarked]) -> send();
