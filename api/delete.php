<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
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

// Collect the post plus all descendant replies, since the row DELETE cascades
// through them and their media files would otherwise be orphaned on disk.
$all_post_ids = [$post_id];
$frontier = [$post_id];

while ($frontier !== []) {
    $placeholders = implode(', ', array_fill(0, count($frontier), '?'));

    $children_stmt = mysqli_prepare($mysqli, '
SELECT `postId`
    FROM `Posts`
    WHERE `parentId` IN (' . $placeholders . ')
');
    mysqli_stmt_bind_param($children_stmt, str_repeat('i', count($frontier)), ...$frontier);
    mysqli_stmt_execute($children_stmt);
    $children_result = mysqli_stmt_get_result($children_stmt);

    $frontier = [];

    while ($row = mysqli_fetch_assoc($children_result)) {
        $all_post_ids[] = (int) $row['postId'];
        $frontier[] = (int) $row['postId'];
    }
}

$doomed_items = [];

foreach (FeedItem::itemsForPosts($all_post_ids) as $post_items) {
    foreach ($post_items as $item) {
        $doomed_items[] = $item;
    }
}

$delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `Posts`
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($delete_stmt, 'i', $post_id);
mysqli_stmt_execute($delete_stmt);

// Only remove files once the rows are actually gone.
foreach ($doomed_items as $item) {
    UploadProcessor::deleteForItem((int) $item -> itemId, (string) $item -> itemType);
}

JSONResponse::success(['deleted' => true]) -> send();
