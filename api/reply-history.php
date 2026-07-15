<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

$parent_id = (int) ($_GET['parentId'] ?? 0);
$before_post_id = (int) ($_GET['beforePostId'] ?? 0);

if ($parent_id === 0 || $before_post_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$mysqli = Database::connection();
$limit = 20;
$fetch_limit = $limit + 1;
$not_banned = 0;

$reply_stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` = ? AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
mysqli_stmt_bind_param($reply_stmt, 'iiii', $parent_id, $not_banned, $before_post_id, $fetch_limit);
mysqli_stmt_execute($reply_stmt);
$reply_result = mysqli_stmt_get_result($reply_stmt);

$reply_rows = [];

while ($row = mysqli_fetch_assoc($reply_result)) {
    $reply_rows[] = $row;
}

$has_more = count($reply_rows) > $limit;

if ($has_more) {
    array_pop($reply_rows);
}

$viewer_id = Auth::id();

$posts = Post::fromRowsWithItems($reply_rows);
$post_ids = array_map(fn ($post) => (int) $post -> postId, $posts);

$reply_counts = Post::replyCountsForPosts($post_ids);
$like_counts = Post::likeCountsForPosts($post_ids);
$liked = $viewer_id !== null ? Post::likedByUserForPosts($post_ids, (int) $viewer_id) : [];
$bookmarked = $viewer_id !== null ? Bookmark::bookmarkedByUserForPosts($post_ids, (int) $viewer_id) : [];

$post_payloads = [];

foreach ($posts as $post) {
    $post_id = (int) $post -> postId;

    $post_payloads[] = $post -> toPayload(
        $reply_counts[$post_id] ?? 0,
        $like_counts[$post_id] ?? 0,
        $liked[$post_id] ?? false,
        $bookmarked[$post_id] ?? false
    );
}

JSONResponse::success([
    'posts' => $post_payloads,
    'hasMore' => $has_more,
]) -> send();
