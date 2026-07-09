<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$current_password = (string) ($payload['currentPassword'] ?? $_POST['currentPassword'] ?? '');
$new_password = (string) ($payload['newPassword'] ?? $_POST['newPassword'] ?? '');
$confirm_password = (string) ($payload['confirmPassword'] ?? $_POST['confirmPassword'] ?? '');

if ($current_user -> passwordHash === null || !password_verify($current_password, $current_user -> passwordHash)) {
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

JSONResponse::success(['changed' => true]) -> send();
