<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$token = (string) ($payload['token'] ?? '');
$new_password = (string) ($payload['newPassword'] ?? '');
$confirm_password = (string) ($payload['confirmPassword'] ?? '');

$user_id = $token !== '' ? PasswordReset::verify($token) : null;

if ($user_id === null) {
    JSONResponse::error('That password reset link is invalid or has expired.', 422) -> send();
}

if (strlen($new_password) < 8) {
    JSONResponse::error('Password must be at least 8 characters.', 422) -> send();
}

if (strlen($new_password) > 72) {
    // bcrypt (password_hash's default) only uses the first 72 bytes and rejects longer input outright.
    JSONResponse::error('Password must be at most 72 characters.', 422) -> send();
}

if ($new_password !== $confirm_password) {
    JSONResponse::error('Passwords do not match.', 422) -> send();
}

$user = User::load($user_id);

// The account can vanish between the token being issued and used (deleted,
// admin-removed) - the token points at nobody, same outcome as an invalid one.
if ($user === null) {
    JSONResponse::error('That password reset link is invalid or has expired.', 422) -> send();
}

// A no-op, same treatment as resubmitting your current email in
// change-email.php: nothing to do, and the token stays valid so a follow-up
// attempt with an actually-different password still works.
if ($user -> passwordHash !== null && password_verify($new_password, $user -> passwordHash)) {
    JSONResponse::success(['reset' => false]) -> send();
}

PasswordReset::consume($token, $new_password);

JSONResponse::success(['reset' => true]) -> send();
