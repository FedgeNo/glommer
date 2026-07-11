<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$target_user_id = (int) ($payload['userId'] ?? $_POST['userId'] ?? 0);

if (!Friendship::removeAccepted((int) $current_user -> userId, $target_user_id)) {
    JSONResponse::error('Not friends with that user', 404) -> send();
}

JSONResponse::success(['removed' => true]) -> send();
