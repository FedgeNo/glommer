<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$client_id = trim((string) ($payload['googleAuthClientId'] ?? ''));
$secret = trim((string) ($payload['googleAuthSecret'] ?? ''));

Settings::set(GoogleAuth::CLIENT_ID_SETTING, $client_id);

// Write-only, same as the Turnstile secret: a blank field keeps the stored
// secret rather than clearing it.
if ($secret !== '') {
    Settings::set(GoogleAuth::CLIENT_SECRET_SETTING, $secret);
}

JSONResponse::success(['saved' => true]) -> send();
