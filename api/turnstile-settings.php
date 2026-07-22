<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

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

// The Google reCAPTCHA keys (the locked-account recovery challenge) share this
// bot-protection form, saved the same way: site key always, secret key only
// when a non-blank value is submitted.
$recaptcha_site_key = trim((string) ($payload['recaptchaSiteKey'] ?? ''));
$recaptcha_secret_key = trim((string) ($payload['recaptchaSecretKey'] ?? ''));

Settings::set(ReCaptcha::SITE_KEY_SETTING, $recaptcha_site_key);

if ($recaptcha_secret_key !== '') {
    Settings::set(ReCaptcha::SECRET_KEY_SETTING, $recaptcha_secret_key);
}

JSONResponse::success(['saved' => true]) -> send();
