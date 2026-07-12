<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$query = trim((string) ($_GET['q'] ?? ''));
$before_post_id = (int) ($_GET['beforePostId'] ?? 0);

if ($query === '') {
    JSONResponse::success(['posts' => [], 'hasMore' => false]) -> send();
}

$mysqli = Database::connection();
$limit = 20;
$fetch_limit = $limit + 1;
$not_banned = 0;

$stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE MATCH(`Posts`.`title`, `Posts`.`description`, `Posts`.`keywords`) AGAINST (? IN NATURAL LANGUAGE MODE)
        AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND (? = 0 OR `Posts`.`postId` < ?)
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
mysqli_stmt_bind_param($stmt, 'siiii', $query, $not_banned, $before_post_id, $before_post_id, $fetch_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$feed_rows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $feed_rows[] = $row;
}

$has_more = count($feed_rows) > $limit;

if ($has_more) {
    array_pop($feed_rows);
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
