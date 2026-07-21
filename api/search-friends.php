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

// A profile's friends are public to read, but an open search endpoint invites
// unauthenticated LIKE queries at any rate they care to send.
if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$query = trim((string) ($payload['q'] ?? ''));
// Whose friends are searched.
$user_id = (int) ($payload['userId'] ?? 0);
// How many cards the client already shows - the next page starts there.
$offset = max(0, (int) ($payload['offset'] ?? 0));

if ($user_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$profile_user = User::load($user_id);

if ($profile_user === null || $profile_user -> banned) {
    JSONResponse::error('User not found', 404) -> send();
}

$page = new FriendSearchList([
    'user' => $profile_user,
    'query' => $query,
    'offset' => $offset,
]) -> toJSON();

$viewer = Auth::user();

$users = [];

foreach ($page['items'] as $friend) {
    $users[] = OtherUser::payloadFor($friend, $viewer);
}

JSONResponse::success([
    'users' => $users,
    'hasMore' => $page['hasMore'],
]) -> send();
