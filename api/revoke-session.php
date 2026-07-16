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

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$token_id = (int) ($payload['tokenId'] ?? 0);

if ($token_id === 0) {
    JSONResponse::error('Invalid device', 422) -> send();
}

// revoke() is scoped to this user's own tokens, so a mismatched or
// someone-else's tokenId simply deletes nothing and reports not-found -
// there's no way to revoke another account's device by guessing an id.
if (!RememberToken::revoke($token_id, (int) Auth::user() -> userId)) {
    JSONResponse::error('Device not found', 404) -> send();
}

JSONResponse::success(['revoked' => true]) -> send();
