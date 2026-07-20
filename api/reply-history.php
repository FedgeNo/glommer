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
// How many replies the client already shows - the next page starts there.
$offset = max(0, (int) ($payload['offset'] ?? 0));

if ($parent_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

// ReplyList owns the query; it loads one page of hydrated replies (items,
// author, the viewer's like/bookmark counts).
$page = new ReplyList(['parentId' => $parent_id, 'offset' => $offset]) -> toJSON();

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
