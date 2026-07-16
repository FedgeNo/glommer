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

$feed_type = (string) ($payload['feedType'] ?? 'global');
$before_post_id = (int) ($payload['beforePostId'] ?? 0);
$profile_user_id = (int) ($payload['userId'] ?? 0);
$tag = strtolower(trim((string) ($payload['tag'] ?? '')));

if ($before_post_id === 0 || !in_array($feed_type, ['global', 'friends', 'user', 'tag'], true)) {
    JSONResponse::error('Invalid request', 422) -> send();
}

if ($feed_type === 'friends' && !Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

if ($feed_type === 'user' && $profile_user_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

if ($feed_type === 'tag' && !preg_match('/^[a-z0-9_]{1,50}$/', $tag)) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$limit = 20;

if ($feed_type === 'friends') {
    $current_user = Auth::user();

    ['rows' => $feed_rows, 'hasMore' => $has_more] = Timeline::rowsForUser((int) $current_user -> userId, $limit, $before_post_id);
} elseif ($feed_type === 'user') {
    ['rows' => $feed_rows, 'hasMore' => $has_more] = Post::userFeedRows($profile_user_id, $limit, $before_post_id);
} elseif ($feed_type === 'tag') {
    ['rows' => $feed_rows, 'hasMore' => $has_more] = Hashtag::postRows($tag, $limit, $before_post_id);
} else {
    ['rows' => $feed_rows, 'hasMore' => $has_more] = Post::globalFeedRows($limit, $before_post_id);
}

$viewer_id = Auth::id();

$posts = Post::fromRowsWithItems($feed_rows);
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
