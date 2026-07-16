<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$parent_id = (int) ($payload['parentId'] ?? 0);
$before_post_id = (int) ($payload['beforePostId'] ?? 0);

if ($parent_id === 0 || $before_post_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$limit = 20;
$fetch_limit = $limit + 1;
$not_banned = 0;

$reply_rows = DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` = ? AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'Post', 'iiii', $parent_id, $not_banned, $before_post_id, $fetch_limit);

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
