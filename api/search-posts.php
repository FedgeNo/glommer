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
$user_id = (int) ($payload['userId'] ?? 0);

if ($query === '') {
    JSONResponse::success(['posts' => [], 'hasMore' => false]) -> send();
}

$page = new SearchFeedList([
    'query' => $query,
    'userId' => $user_id,
    'offset' => $offset,
]) -> toJSON();

$post_payloads = [];

foreach ($page['items'] as $post) {
    $post_payloads[] = $post -> toPayload(
        (int) $post -> replyCount,
        (int) $post -> likeCount,
        (bool) $post -> liked,
        (bool) $post -> bookmarked
    );
}

JSONResponse::success([
    'posts' => $post_payloads,
    'hasMore' => $page['hasMore'],
]) -> send();
