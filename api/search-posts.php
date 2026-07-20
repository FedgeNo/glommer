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

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$query = trim((string) ($payload['q'] ?? ''));
// How many results the client already shows - the next page starts there.
$offset = max(0, (int) ($payload['offset'] ?? 0));
// Optional: restrict the search to one author's posts (the per-user search on a
// profile page). 0 means "everyone" - the global /search behaviour.
$author_id = (int) ($payload['userId'] ?? 0);

if ($query === '') {
    JSONResponse::success(['posts' => [], 'hasMore' => false]) -> send();
}

$limit = 20;
$fetch_limit = $limit + 1;
$viewer_id = (int) Auth::id();

$feed_rows = PostSearch::matchingRows($query, $author_id, $fetch_limit, $offset);

$has_more = count($feed_rows) > $limit;

if ($has_more) {
    array_pop($feed_rows);
}

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
