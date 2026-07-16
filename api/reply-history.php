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

// ReplyList owns the query; it fetches PAGE_SIZE + 1 hydrated replies (items,
// author, the viewer's like/bookmark counts) into its contents.
$posts = (new ReplyList(['parentId' => $parent_id, 'before' => $before_post_id])) -> contents;

$has_more = count($posts) > ReplyList::PAGE_SIZE;

if ($has_more) {
    array_pop($posts);
}

$post_payloads = [];

foreach ($posts as $post) {
    $post_payloads[] = $post -> toPayload(
        (int) $post -> replyCount,
        (int) $post -> likeCount,
        (bool) $post -> liked,
        (bool) $post -> bookmarked
    );
}

JSONResponse::success([
    'posts' => $post_payloads,
    'hasMore' => $has_more,
]) -> send();
