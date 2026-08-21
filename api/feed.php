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

$feed_type = (string) ($payload['feedType'] ?? 'global');
// How many posts the client already shows - the next page starts there.
$offset = max(0, (int) ($payload['offset'] ?? 0));
$profile_user_id = (int) ($payload['userId'] ?? 0);
$tag = strtolower(trim((string) ($payload['tag'] ?? '')));
$cursor = is_array($payload['cursor'] ?? null) ? $payload['cursor'] : [];
$before_post_id = (int) ($cursor['postId'] ?? 0);
$before_sort_at = (string) ($cursor['sortAt'] ?? '');

if ($before_sort_at !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $before_sort_at) !== 1) {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
}

if (!Auth::check()) {
    $rate_keys = [
        'feed-ip:' . (ServerURL::clientIP() ?? 'unknown'),
        'feed-client:' . hash('sha256', session_id()),
    ];

    foreach ($rate_keys as $rate_key) {
        if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
            JSONResponse::localizedError('tooManyRequestsPleaseSlowDown', 429) -> send();
        }

        RateLimiter::recordAttempt($rate_key);
    }
}

if (!in_array($feed_type, ['global', 'friends', 'user', 'tag', 'relay'], true)) {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
}

if ($feed_type === 'friends' && !Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

// The firehose is other servers' writing, held to the same members-only rule
// as every other remote-origin listing.
if ($feed_type === 'relay' && !Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

if ($feed_type === 'user' && $profile_user_id === 0) {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
}

// A remote account's posts are members-only - the same rule its profile page
// (user.php), permalink (post.php) and RSS feed (user-rss-feed.php) apply.
// Without this, paging the profile feed by userId would hand a logged-out
// caller the very posts those gates withhold.
if ($feed_type === 'user' && !Auth::check() && User::load($profile_user_id) ?-> remoteActorURI !== null) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

if ($feed_type === 'tag' && !preg_match('/^[a-z0-9_]{1,50}$/', $tag)) {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
}

// Each feed owns its query and loads one page of hydrated posts (items,
// author, the viewer's like/bookmark counts).
$feed = match ($feed_type) {
    'friends' => new FriendsFeedList([
        'userId' => (int) Auth::user() -> userId,
        'beforeSortAt' => $before_sort_at !== '' && $before_post_id > 0 ? $before_sort_at : null,
        'beforePostId' => $before_sort_at !== '' && $before_post_id > 0 ? $before_post_id : null,
    ]),
    'user' => new ProfileFeedList(['userId' => $profile_user_id, 'offset' => $offset]),
    'tag' => new TagFeedList(['tag' => $tag, 'offset' => $offset]),
    'relay' => new RelayFeedList(['offset' => $offset]),
    default => new GlobalFeedList(['beforePostId' => $before_post_id > 0 ? $before_post_id : null]),
};

$page = $feed -> toJSON();

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
    'cursor' => $page['cursor'],
]) -> send();
