<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$target_user_id = (int) ($payload['userId'] ?? $_POST['userId'] ?? 0);

if ($target_user_id === $current_user -> userId) {
    JSONResponse::error('You can\'t block yourself.', 422) -> send();
}

if (User::load($target_user_id) === null) {
    JSONResponse::error('User not found', 404) -> send();
}

Block::create($current_user -> userId, $target_user_id);

JSONResponse::success(['blocked' => true]) -> send();
