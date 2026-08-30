<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$email = trim((string) ($payload['email'] ?? ''));

$ip_rate_key = 'forgot-password-ip:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($ip_rate_key, 5, 900)) {
    JSONResponse::localizedError('tooManyPasswordResetRequestsPleaseTryAgainLater', 429) -> send();
}

if ($email !== '') {
    RateLimiter::recordAttempt($ip_rate_key);

    // The IP budget stops one client spraying accounts; this address budget
    // stops distributed clients repeatedly targeting one account. It is
    // applied whether the submitted address exists or not, so the outward
    // behaviour does not become an account-existence oracle.
    if (!PasswordReset::allowRequestFor($email)) {
        JSONResponse::localizedError('tooManyPasswordResetRequestsPleaseTryAgainLater', 429) -> send();
    }

    $user = DB::row('
SELECT *
    FROM `Users`
    WHERE `email` = ?
', 'User', 's', $email);

    if ($user !== null) {
        PasswordReset::sendFor($user);
    }
}

// Always the same response regardless of whether the email matched, to avoid
// leaking which emails have accounts.
JSONResponse::success(['sent' => true]) -> send();
