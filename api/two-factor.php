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
$action = (string) ($payload['action'] ?? '');
$current_password = (string) ($payload['currentPassword'] ?? '');

if ($action !== 'enable' && $action !== 'disable') {
    JSONResponse::error('Invalid action', 422) -> send();
}

// Throttle current-password guessing here too - see change-password.php.
// Shares the per-user password-verify key with the other password-confirming
// endpoints so guesses can't be multiplied across them.
$password_rate_key = 'password-verify:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($password_rate_key, 10, 900)) {
    JSONResponse::error('Too many attempts. Please try again later.', 429) -> send();
}

// Both directions require the current password - turning the protection off
// is at least as security-sensitive as turning it on, so neither can be done
// from a merely-open session without proving the password again.
if (!$current_user -> verifyPassword($current_password)) {
    RateLimiter::recordAttempt($password_rate_key);

    JSONResponse::error('Current password is incorrect', 422) -> send();
}

TwoFactor::setEnabled((int) $current_user -> userId, $action === 'enable');
Auth::clearUserCache();

JSONResponse::success(['enabled' => $action === 'enable']) -> send();
