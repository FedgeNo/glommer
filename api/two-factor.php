<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$action = (string) ($payload['action'] ?? '');
$current_password = (string) ($payload['currentPassword'] ?? '');

if ($action !== 'enable' && $action !== 'disable' && $action !== 'regenerate-recovery') {
    JSONResponse::localizedError('invalidAction', 422) -> send();
}

// Throttle current-password guessing here too - see change-password.php.
// Shares the per-user password-verify key with the other password-confirming
// endpoints so guesses can't be multiplied across them.
$password_rate_key = 'password-verify:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($password_rate_key, 10, 900)) {
    JSONResponse::localizedError('tooManyAttemptsPleaseTryAgainLater', 429) -> send();
}

// Every action requires the current password - turning the protection off is
// at least as security-sensitive as turning it on, and replacing the recovery
// codes invalidates the ones the user has saved - so none can be done from a
// merely-open session without proving the password again.
if (!$current_user -> verifyPassword($current_password)) {
    RateLimiter::recordAttempt($password_rate_key);

    JSONResponse::fieldError('currentPassword', JSONResponse::localized('notCurrentPassword')) -> send();
}

$user_id = (int) $current_user -> userId;

if ($action === 'regenerate-recovery') {
    if (!TwoFactor::isEnabled($current_user)) {
        JSONResponse::localizedError('twoFactorAuthenticationIsNotOn', 422) -> send();
    }

    JSONResponse::success([
        'enabled' => true,
        'recoveryCodes' => TwoFactor::generateRecoveryCodes($user_id),
    ]) -> send();
}

TwoFactor::setEnabled($user_id, $action === 'enable');
Auth::clearUserCache();

// Enabling issues the recovery-code batch in the same response - the only
// time the codes exist in plain text, so the client shows them right away.
$response = ['enabled' => $action === 'enable'];

if ($action === 'enable') {
    $response['recoveryCodes'] = TwoFactor::generateRecoveryCodes($user_id);
}

JSONResponse::success($response) -> send();
