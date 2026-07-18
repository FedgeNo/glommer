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

// How many posts the client already shows - the next page starts there,
// same offset pagination as api/search-users.php.
$offset = max(0, (int) ($payload['offset'] ?? 0));

// BookmarkList owns the query; it fetches PAGE_SIZE + 1 hydrated posts (items,
// author, the viewer's like counts) into its contents, ordered by when each
// was bookmarked.
$posts = (new BookmarkList([
    'userId' => (int) $current_user -> userId,
    'offset' => $offset,
])) -> contents;

$has_more = count($posts) > BookmarkList::PAGE_SIZE;

if ($has_more) {
    array_pop($posts);
}

$post_payloads = [];

foreach ($posts as $post) {
    $post_payloads[] = $post -> toPayload(
        (int) $post -> replyCount,
        (int) $post -> likeCount,
        (bool) $post -> liked,
        // Every post here is by definition bookmarked - this is the bookmarks list.
        true
    );
}

JSONResponse::success([
    'posts' => $post_payloads,
    'hasMore' => $has_more,
]) -> send();
