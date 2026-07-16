<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Who's mid-login is carried in the session by api/login.php - a pending
// state, NOT a logged-in one (Auth::check() is still false here). No pending
// user means there's nothing to verify (direct hit, or an expired/cleared
// session).
$user_id = $_SESSION['pending2faUserId'] ?? null;

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
    unset($_SESSION['pending2faUserId'], $_SESSION['pending2faRememberMe']);

    JSONResponse::error('This account can no longer log in.', 403) -> send();
}

$remember_me = ($_SESSION['pending2faRememberMe'] ?? false) === true;

// The pending flags must go before Auth::login() regenerates the session,
// so a completed 2FA can never be replayed against the same pending state.
unset($_SESSION['pending2faUserId'], $_SESSION['pending2faRememberMe']);

Auth::login($user);

if ($remember_me) {
    RememberToken::issue((int) $user -> userId);
}

JSONResponse::success(['loggedIn' => true]) -> send();
