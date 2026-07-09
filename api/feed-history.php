<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

$feed_type = (string) ($_GET['feedType'] ?? 'global');
$before_post_id = (int) ($_GET['beforePostId'] ?? 0);

if ($before_post_id === 0 || !in_array($feed_type, ['global', 'friends'], true)) {
    JSONResponse::error('Invalid request', 422) -> send();
}

if ($feed_type === 'friends' && !Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$mysqli = Database::connection();
$limit = 20;

if ($feed_type === 'friends') {
    $current_user = Auth::user();

    ['rows' => $feed_rows, 'hasMore' => $has_more] = Timeline::rowsForUser((int) $current_user -> userId, $limit, $before_post_id);
} else {
    $fetch_limit = $limit + 1;
    $not_banned = 0;

    $feed_stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
    mysqli_stmt_bind_param($feed_stmt, 'iii', $not_banned, $before_post_id, $fetch_limit);
    mysqli_stmt_execute($feed_stmt);
    $feed_result = mysqli_stmt_get_result($feed_stmt);

    $feed_rows = [];

    while ($row = mysqli_fetch_assoc($feed_result)) {
        $feed_rows[] = $row;
    }

    $has_more = count($feed_rows) > $limit;

    if ($has_more) {
        array_pop($feed_rows);
    }
}

$viewer_id = Auth::id();

$posts = Post::fromRowsWithItems($feed_rows);
$post_ids = array_map(fn ($post) => (int) $post -> postId, $posts);

$reply_counts = Post::replyCountsForPosts($post_ids);
$like_counts = Post::likeCountsForPosts($post_ids);
$liked = $viewer_id !== null ? Post::likedByUserForPosts($post_ids, (int) $viewer_id) : [];

$post_payloads = [];

foreach ($posts as $post) {
    $post_id = (int) $post -> postId;

    $post_payloads[] = $post -> toPayload(
        $reply_counts[$post_id] ?? 0,
        $like_counts[$post_id] ?? 0,
        $liked[$post_id] ?? false
    );
}

JSONResponse::success([
    'posts' => $post_payloads,
    'hasMore' => $has_more,
]) -> send();
