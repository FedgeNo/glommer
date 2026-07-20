<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$other_user_id = (int) ($payload['otherUserId'] ?? 0);
// How many messages the client already shows - the next (older) page starts
// there, counted from the newest.
$offset = max(0, (int) ($payload['offset'] ?? 0));

if ($other_user_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

if (Block::exists($current_user -> userId, $other_user_id)) {
    JSONResponse::error('You can\'t message this user.', 403) -> send();
}

$page = new MessageList([
    'userId' => (int) $current_user -> userId,
    'otherUserId' => $other_user_id,
    'offset' => $offset,
]) -> toJSON();

JSONResponse::success([
    'messages' => array_reverse($page['items']),
    'hasMore' => $page['hasMore'],
]) -> send();
