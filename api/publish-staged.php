<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

$payload = json_decode((string) file_get_contents('php://input'), true);
$staged = StagedPost::load((int) ($payload['stagedPostId'] ?? 0));

if ($staged === null || (int) $staged -> userId !== Auth::id()) {
    JSONResponse::error('Not found', 404) -> send();
}

$post_id = $staged -> publish();

if ($post_id === null) {
    // The worker's clock beat the button by a moment - the post exists, this
    // click just wasn't the one that made it.
    JSONResponse::success(['published' => true]) -> send();
}

JSONResponse::success(['published' => true, 'postId' => $post_id]) -> send();
