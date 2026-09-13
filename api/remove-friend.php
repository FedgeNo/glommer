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

if (!Friendship::removeAccepted((int) $current_user -> userId, $target_user_id)) {
    JSONResponse::localizedError('notFriendsWithThatUser', 404) -> send();
}

JSONResponse::success(['removed' => true]) -> send();
