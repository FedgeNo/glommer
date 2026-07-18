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
// How many posts the client already shows - the next page starts there.
$offset = max(0, (int) ($payload['offset'] ?? 0));
$profile_user_id = (int) ($payload['userId'] ?? 0);
$tag = strtolower(trim((string) ($payload['tag'] ?? '')));

if (!in_array($feed_type, ['global', 'friends', 'user', 'tag'], true)) {
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

// FeedList owns the query; the friends feed reads the viewer's own timeline,
// the others the profile/tag/global posts. It fetches PAGE_SIZE + 1 hydrated
// (items, author, the viewer's like/bookmark counts) into its contents.
$feed_user_id = $feed_type === 'friends' ? (int) Auth::user() -> userId : $profile_user_id;

$posts = (new FeedList([
    'feedType' => $feed_type,
    'userId' => $feed_user_id,
    'tag' => $tag,
    'offset' => $offset,
])) -> contents;

$has_more = count($posts) > FeedList::PAGE_SIZE;

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
