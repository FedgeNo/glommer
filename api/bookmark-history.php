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

// Private list - unlike feed-history.php (which serves public feeds and only
// gates the 'friends' feedType), every request here is the viewer's own data.
if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$before_created_at = isset($payload['beforeCreatedAt']) && $payload['beforeCreatedAt'] !== '' ? (string) $payload['beforeCreatedAt'] : null;
$before_post_id = isset($payload['beforePostId']) && $payload['beforePostId'] !== '' ? (int) $payload['beforePostId'] : null;

if ($before_created_at === null || $before_post_id === null) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$limit = 20;

$bookmarks = Bookmark::rowsForUser((int) $current_user -> userId, $limit, $before_created_at, $before_post_id);

$posts = Post::fromRowsWithItems($bookmarks['rows']);
$post_ids = array_map(fn ($post) => (int) $post -> postId, $posts);

$reply_counts = Post::replyCountsForPosts($post_ids);
$like_counts = Post::likeCountsForPosts($post_ids);
$liked = Post::likedByUserForPosts($post_ids, (int) $current_user -> userId);

$post_payloads = [];

foreach ($posts as $post) {
    $post_id = (int) $post -> postId;

    $post_payloads[] = $post -> toPayload(
        $reply_counts[$post_id] ?? 0,
        $like_counts[$post_id] ?? 0,
        $liked[$post_id] ?? false,
        // Every post here is by definition bookmarked - this is the bookmarks list.
        true
    );
}

JSONResponse::success([
    'posts' => $post_payloads,
    'hasMore' => $bookmarks['hasMore'],
    'oldestBookmarkCreatedAt' => $bookmarks['oldestCreatedAt'],
    'oldestBookmarkPostId' => $bookmarks['oldestPostId'],
]) -> send();
