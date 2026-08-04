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
$post_id = (int) ($payload['itemId'] ?? 0);

$owner = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

if ($owner === null || (int) $owner -> userId !== $current_user -> userId) {
    JSONResponse::error('Not your post', 403) -> send();
}

// Read before the row goes: once it is deleted there is nothing left to build
// the URI from, and the followers still need telling.
$object_uri = FediversePublisher::objectURIFor($post_id);

Post::delete($post_id);

if ($object_uri !== null) {
    FediversePublisher::deleted($object_uri, $current_user);
}

JSONResponse::success(['deleted' => true]) -> send();
