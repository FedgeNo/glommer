<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

Auth::requireLogin();

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// The aliases first: they are only a permission, and saving them has to work
// even when the move itself is refused - moving IN to this account depends on
// them, and that is a separate act from moving out of it.
$aliases = preg_split('/\R/', (string) ($payload['alsoKnownAs'] ?? '')) ?: [];

ActivityPubMove::setAliases($current_user, $aliases);

$destination = trim((string) ($payload['movedTo'] ?? ''));

if ($destination === '') {
    JSONResponse::success(['saved' => true, 'moved' => false]) -> send();
}

// Already there: saying so rather than sending the same Move again, which
// would ask every follower to re-follow an account they are already on.
if ($destination === $current_user -> movedToURI) {
    JSONResponse::success(['saved' => true, 'moved' => true]) -> send();
}

$result = ActivityPubMove::publish($current_user, $destination);

if (!$result['ok']) {
    JSONResponse::error((string) $result['error'], 422) -> send();
}

JSONResponse::success(['saved' => true, 'moved' => true]) -> send();
