<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$target_user_id = (int) ($payload['userId'] ?? 0);

if ($target_user_id === $current_user -> userId) {
    JSONResponse::error('You can\'t block yourself.', 422) -> send();
}

$target_user = User::load($target_user_id);

if ($target_user === null) {
    JSONResponse::error('User not found', 404) -> send();
}

Block::create($current_user -> userId, $target_user_id);

// Tell their server, and drop the federated follows either way. A block that
// stops at this server is half a block - their side keeps delivering, because
// it was never told.
ActivityPubBlock::severFollows($target_user_id, $current_user -> userId, (string) $target_user -> remoteActorURI);
ActivityPubBlock::published($current_user, $target_user, true);

JSONResponse::success(['blocked' => true]) -> send();
