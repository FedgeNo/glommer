<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// Public, like the thread it pages: a permalink shows its replies to anyone.
// Paced per client for the same reason map-posts and nearby-history are - a
// page here hydrates every reply with its media, author and the viewer's like
// and bookmark state, so it costs meaningfully more than the single indexed
// read it looks like, and nothing else stands between it and a loop.
$rate_key = 'reply-history:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
    JSONResponse::localizedError('tooManyRequestsPleaseSlowDown', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

$parent_id = (int) ($payload['parentId'] ?? 0);
// How many replies the client already shows - the next page starts there.
$offset = max(0, (int) ($payload['offset'] ?? 0));

if ($parent_id === 0) {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
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
