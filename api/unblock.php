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

$payload = APIRequest::read(['userId' => 'integer']);
$target_user_id = (int) ($payload['userId'] ?? 0);
$target_user = User::load($target_user_id);

if ($target_user === null) {
    JSONResponse::localizedError('userNotFound', 404) -> send();
}

Block::remove($current_user -> userId, $target_user_id);

// Their server was told about the block, so it has to be told about the lift -
// otherwise it keeps enforcing one this side no longer holds.
ActivityPubBlock::published($current_user, $target_user, false);

JSONResponse::success(OtherUser::payloadFor($target_user, $current_user)) -> send();
