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

// The site needs at least one admin account to function (moderation,
// reports, settings) - the same immunity every other admin-targeting action
// (ban, report) already gives userId 1.
if ((int) $current_user -> userId === 1) {
    JSONResponse::error('This account can\'t be deleted.', 422) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$current_password = (string) ($payload['currentPassword'] ?? '');

// Throttle current-password guessing here too - see change-password.php. Same
// per-user key, so guesses can't be multiplied across the password-confirming
// endpoints.
$password_rate_key = 'password-verify:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($password_rate_key, 10, 900)) {
    JSONResponse::error('Too many attempts. Please try again later.', 429) -> send();
}

if ($current_user -> passwordHash === null || !password_verify($current_password, $current_user -> passwordHash)) {
    RateLimiter::recordAttempt($password_rate_key);

    JSONResponse::error('Current password is incorrect', 422) -> send();
}

User::delete((int) $current_user -> userId);

Auth::logout();
RememberToken::forget();

JSONResponse::success(['deleted' => true]) -> send();
