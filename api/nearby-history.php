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

// Public, like the feed it pages: the posts are the same public ones, just
// selected by proximity. Paced per client because each call ranks every located
// post, which is more work than an ordinary indexed feed page.
$rate_key = 'nearby-history:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
    JSONResponse::localizedError('tooManyRequestsPleaseSlowDown', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

// The client sends back the same origin it used for page one - re-reading the
// device's location per page would shift the result set under the reader.
$origin = Coordinates::parse($payload['latitude'] ?? null, $payload['longitude'] ?? null);

if ($origin === null) {
    JSONResponse::localizedError('invalidLocation', 422) -> send();
}

$offset = max(0, (int) ($payload['offset'] ?? 0));

$page = new NearbyFeedList([
    'latitude' => $origin -> latitude,
    'longitude' => $origin -> longitude,
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
