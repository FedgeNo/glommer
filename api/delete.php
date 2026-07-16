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
$owner = mysqli_fetch_assoc($owner_result);

if ($owner === null || (int) $owner['userId'] !== $current_user -> userId) {
    JSONResponse::error('Not your post', 403) -> send();
}

Post::delete($post_id);

JSONResponse::success(['deleted' => true]) -> send();
