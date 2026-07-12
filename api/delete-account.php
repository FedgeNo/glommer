<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

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
$current_password = (string) ($payload['currentPassword'] ?? '');

if ($current_user -> passwordHash === null || !password_verify($current_password, $current_user -> passwordHash)) {
    JSONResponse::error('Current password is incorrect', 422) -> send();
}

User::delete((int) $current_user -> userId);

Auth::logout();
RememberToken::forget();

JSONResponse::success(['deleted' => true]) -> send();
