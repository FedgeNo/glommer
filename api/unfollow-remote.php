<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$target_user_id = (int) ($payload['userId'] ?? 0);

$target_user = User::load($target_user_id);

if ($target_user === null || $target_user -> remoteActorURI === null) {
    JSONResponse::localizedError('thatIsNotAFediverseAccount', 404) -> send();
}

RemoteFollow::remove((int) $current_user -> userId, $target_user -> remoteActorURI);

// The one-way link is what the profile renders its button from, so it goes
// whether or not a follow row was still there to remove.
Friendship::removeFollow((int) $current_user -> userId, $target_user_id);

JSONResponse::success(['following' => false]) -> send();
