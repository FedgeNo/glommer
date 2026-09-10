<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

$current_user = Auth::user();

// Liking is a toggle, so it can be repeated indefinitely; paced so it cannot
// be used to hammer the database (the notification itself is deduplicated).
$like_rate_key = 'like:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($like_rate_key, 120, 600)) {
    JSONResponse::localizedError('youReDoingThatVeryQuicklyPleaseWaitAMoment', 429) -> send();
}

RateLimiter::recordAttempt($like_rate_key);

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$post_id = (int) ($payload['itemId'] ?? 0);

$owner = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

if ($owner === null) {
    JSONResponse::localizedError('postNotFound', 404) -> send();
}

if (Block::exists($current_user -> userId, (int) $owner -> userId)) {
    JSONResponse::localizedError('unableToLikeThisPost', 403) -> send();
}

if (Like::exists($current_user -> userId, $post_id)) {
    Like::remove($current_user -> userId, $post_id);
    $liked = false;
} else {
    $liked = true;

    // A duplicate request still answers "liked", but adds nothing twice.
    if (Like::create($current_user -> userId, $post_id)) {
        Notification::create((int) $owner -> userId, $current_user -> userId, 'like', $post_id);
    }
}

$post = DB::row('
SELECT `likeCount`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);
$count = $post?-> likeCount ?? 0;

// Only says anything when the post came from elsewhere - liking a local post is
// this server's own business and there is nobody to tell.
ActivityPubReaction::publishLike($post_id, $current_user, $liked);

JSONResponse::success(['liked' => $liked, 'count' => $count]) -> send();
