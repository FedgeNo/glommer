<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$rate_key = 'login:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 10, 900)) {
    JSONResponse::error('Too many login attempts. Please try again later.', 429) -> send();
}

$identifier = trim((string) ($payload['identifier'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$captcha_token = is_string($payload['captchaToken'] ?? null) ? $payload['captchaToken'] : null;

// A second limit keyed on the account, not the address - the IP limit alone
// never trips for a guessing attack spread across many machines that all
// target one account.
$account_rate_key = 'login-user:' . strtolower($identifier);

if ($identifier === '' || $password === '') {
    JSONResponse::error('Username/email and password are required.', 422) -> send();
}

if (RateLimiter::tooManyAttempts($account_rate_key, 10, 900)) {
    JSONResponse::error('Too many login attempts for this account. Please try again later.', 429) -> send();
}

// A no-op when Turnstile isn't configured. Fail open on a Cloudflare outage:
// a definite bad/absent token is still rejected, but an unreachable
// Cloudflare mustn't lock every user out of an account they can already
// prove with a password (which is rate-limited too).
if (!Turnstile::verify($captcha_token, ServerURL::clientIP(), fail_open_on_error: true)) {
    JSONResponse::error('Captcha verification failed. Please try again.', 422) -> send();
}

$user = Auth::verifyCredentials($identifier, $password);

if ($user === null) {
    // Only failed attempts count toward the limits, so legitimate logins
    // never eat into them.
    RateLimiter::recordAttempt($rate_key);
    RateLimiter::recordAttempt($account_rate_key);

    JSONResponse::error('Incorrect username/email or password.', 422) -> send();
}

if ($user -> banned) {
    // Correct password but banned: show the reason a moderator gave, rather
    // than logging them in.
    $message = $user -> banReason !== null && $user -> banReason !== ''
        ? 'Your account has been banned. Reason: ' . $user -> banReason
        : 'Your account has been banned.';

    JSONResponse::error($message, 403) -> send();
}

Auth::login($user);

if (($payload['rememberMe'] ?? false) === true) {
    RememberToken::issue((int) $user -> userId);
}

JSONResponse::success(['loggedIn' => true]) -> send();
