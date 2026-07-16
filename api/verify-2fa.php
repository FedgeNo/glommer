<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

// Already logged in: there's nothing to verify, and honoring a leftover
// pending id here would let an established session be switched to a different
// account by guessing that account's code. A completed login clears the
// pending state (Auth::login), so this only catches a stale/crossed request.
if (Auth::check()) {
    JSONResponse::error('Already logged in', 403) -> send();
}

// Who's mid-login is carried in the session by api/login.php - a pending
// state, NOT a logged-in one (Auth::check() is still false here). No pending
// user means there's nothing to verify (direct hit, or an expired/cleared
// session).
$user_id = $_SESSION['pending2FAUserId'] ?? null;

if (!is_int($user_id)) {
    JSONResponse::error('No login in progress. Please start again.', 401) -> send();
}

// Rate-limit code guesses per account, on top of TwoFactor's own per-code
// attempt cap - stops someone from restarting login repeatedly to farm fresh
// codes and guess against each.
$rate_key = 'verify-2fa:' . $user_id;

if (RateLimiter::tooManyAttempts($rate_key, 10, 900)) {
    JSONResponse::error('Too many attempts. Please try again later.', 429) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$code = trim((string) ($payload['code'] ?? ''));

if ($code === '') {
    JSONResponse::error('Enter the code we emailed you.', 422) -> send();
}

if (!TwoFactor::verifyCode($user_id, $code)) {
    RateLimiter::recordAttempt($rate_key);

    JSONResponse::error('That code is incorrect or has expired.', 422) -> send();
}

$user = User::load($user_id);

if ($user === null || $user -> banned) {
    unset($_SESSION['pending2FAUserId'], $_SESSION['pending2FARememberMe']);

    JSONResponse::error('This account can no longer log in.', 403) -> send();
}

$remember_me = ($_SESSION['pending2FARememberMe'] ?? false) === true;

// The pending flags must go before Auth::login() regenerates the session,
// so a completed 2FA can never be replayed against the same pending state.
unset($_SESSION['pending2FAUserId'], $_SESSION['pending2FARememberMe']);

Auth::login($user);
LoginFingerprint::record((int) $user -> userId);

if ($remember_me) {
    RememberToken::issue((int) $user -> userId);
}

JSONResponse::success(['loggedIn' => true]) -> send();
