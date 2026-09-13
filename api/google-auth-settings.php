<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

$payload = APIRequest::read(['googleAuthClientId' => 'text', 'googleAuthSecret' => 'text']);

$client_id = trim((string) ($payload['googleAuthClientId'] ?? ''));
$secret = trim((string) ($payload['googleAuthSecret'] ?? ''));

Settings::set(GoogleAuth::CLIENT_ID_SETTING, $client_id);

// Write-only, same as the Turnstile secret: a blank field keeps the stored
// secret rather than clearing it.
if ($secret !== '') {
    Settings::set(GoogleAuth::CLIENT_SECRET_SETTING, $secret);
}

JSONResponse::success(['saved' => true]) -> send();
