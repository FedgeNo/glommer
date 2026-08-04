<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$post_id = (int) ($payload['postId'] ?? 0);

if ($post_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$owner = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

if ($owner === null) {
    JSONResponse::error('Post not found', 404) -> send();
}

if (Block::exists((int) $current_user -> userId, (int) $owner -> userId)) {
    JSONResponse::error('Unable to repost this.', 403) -> send();
}

$user_id = (int) $current_user -> userId;

if (Repost::exists($user_id, $post_id)) {
    Repost::remove($user_id, $post_id);
    $reposted = false;
} else {
    // Refused rather than silently ignored: the only reason is that it is the
    // reposter's own post, and they should be told. Also, this can't happen
    // unless the user has a modified client because the button doesn't show
    // on your own posts.
    if (!Repost::create($user_id, $post_id)) {
        JSONResponse::error('You can\'t repost your own post.', 422) -> send();
    }

    $reposted = true;

    Notification::create((int) $owner -> userId, $user_id, 'repost', $post_id);
}

Repost::publish($current_user, $post_id, $reposted);

JSONResponse::success([
    'reposted' => $reposted,
    'count' => ActivityPubReaction::announceCount($post_id),
]) -> send();
