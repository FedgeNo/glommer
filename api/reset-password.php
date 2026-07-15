<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$token = (string) ($payload['token'] ?? '');
$new_password = (string) ($payload['newPassword'] ?? '');
$confirm_password = (string) ($payload['confirmPassword'] ?? '');

if ($token === '' || PasswordReset::verify($token) === null) {
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

PasswordReset::consume($token, $new_password);

JSONResponse::success(['reset' => true]) -> send();
