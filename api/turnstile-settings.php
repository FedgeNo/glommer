<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$site_key = trim((string) ($payload['turnstileSiteKey'] ?? ''));
$secret_key = trim((string) ($payload['turnstileSecretKey'] ?? ''));

Settings::set(Turnstile::SITE_KEY_SETTING, $site_key);

// The secret key is write-only: a blank field means "leave the stored secret
// unchanged" (it's never rendered back into the form), so only overwrite it
// when an actual value is submitted.
if ($secret_key !== '') {
    Settings::set(Turnstile::SECRET_KEY_SETTING, $secret_key);
}

JSONResponse::success(['saved' => true]) -> send();
