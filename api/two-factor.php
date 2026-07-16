<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

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

// Both directions require the current password - turning the protection off
// is at least as security-sensitive as turning it on, so neither can be done
// from a merely-open session without proving the password again.
if ($current_user -> passwordHash === null || !password_verify($current_password, $current_user -> passwordHash)) {
    JSONResponse::error('Current password is incorrect', 422) -> send();
}

TwoFactor::setEnabled((int) $current_user -> userId, $action === 'enable');
Auth::clearUserCache();

JSONResponse::success(['enabled' => $action === 'enable']) -> send();
