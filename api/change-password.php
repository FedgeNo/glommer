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
$mysqli = DB::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$current_password = (string) ($payload['currentPassword'] ?? $_POST['currentPassword'] ?? '');
$new_password = (string) ($payload['newPassword'] ?? $_POST['newPassword'] ?? '');
$confirm_password = (string) ($payload['confirmPassword'] ?? $_POST['confirmPassword'] ?? '');

// Throttle current-password guessing: anyone holding a logged-in session (a
// hijacked cookie, a borrowed browser) could otherwise brute-force the
// account's real password through this verify path. Keyed per user and shared
// with the other password-confirming endpoints (change-email, delete-account)
// so the attempts can't be multiplied across them.
$password_rate_key = 'password-verify:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($password_rate_key, 10, 900)) {
    JSONResponse::error('Too many attempts. Please try again later.', 429) -> send();
}

if ($current_user -> passwordHash === null || !password_verify($current_password, $current_user -> passwordHash)) {
    RateLimiter::recordAttempt($password_rate_key);

    JSONResponse::error('Current password is incorrect', 422) -> send();
}

if (strlen($new_password) < 8) {
    JSONResponse::error('New password must be at least 8 characters', 422) -> send();
}

// bcrypt (password_hash's default) only uses the first 72 bytes and rejects longer input outright.
if (strlen($new_password) > 72) {
    JSONResponse::error('New password must be at most 72 characters', 422) -> send();
}

if ($new_password !== $confirm_password) {
    JSONResponse::error('New passwords do not match', 422) -> send();
}

$hash = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `passwordHash` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'si', $hash, $current_user -> userId);
mysqli_stmt_execute($stmt);

// The old password's sessions and remember-me tokens die with it - any other
// browser (or thief) holding one gets logged out. This session proved the
// current password, so it adopts the new version and stays.
$_SESSION['sessionVersion'] = User::bumpSessionVersion((int) $current_user -> userId);
RememberToken::purgeForUser((int) $current_user -> userId);
Auth::clearUserCache();

JSONResponse::success(['changed' => true]) -> send();
