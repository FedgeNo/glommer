<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

// Already logged in: there's nothing to verify, and honoring a leftover
// pending id here would let an established session be switched to a different
// account by guessing that account's code. A completed login clears the
// pending state (Auth::login), so this only catches a stale/crossed request.
if (Auth::check()) {
    JSONResponse::localizedError('alreadyLoggedIn', 403) -> send();
}

// Who's mid-login is carried in the session by api/login.php - a pending
// state, NOT a logged-in one (Auth::check() is still false here). No pending
// user means there's nothing to verify (direct hit, or an expired/cleared
// session).
$user = Auth::pendingTwoFactorUser();

if ($user === null) {
    JSONResponse::localizedError('noLoginInProgressPleaseStartAgain', 401) -> send();
}

$user_id = (int) $user -> userId;

// Rate-limit code guesses per account, on top of TwoFactor's own per-code
// attempt cap - stops someone from restarting login repeatedly to farm fresh
// codes and guess against each.
$rate_key = 'verify-2fa:' . $user_id;

if (RateLimiter::tooManyAttempts($rate_key, 10, 900)) {
    JSONResponse::localizedError('tooManyAttemptsPleaseTryAgainLater', 429) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$code = $payload['code'] ?? '';

if (!is_string($code)) {
    JSONResponse::localizedError('malformedRequest', 422) -> send();
}

$code = trim($code);

if ($code === '') {
    JSONResponse::fieldError('code', JSONResponse::localized('enterEmailedCode')) -> send();
}

// One field, either credential: the short emailed code or one of the
// single-use recovery codes (the way in when the code email can't be sent).
// Emailed code first - it's the common case - then recovery.
if (!TwoFactor::verifyCode($user_id, $code) && !TwoFactor::verifyRecoveryCode($user_id, $code)) {
    RateLimiter::recordAttempt($rate_key);

    JSONResponse::fieldError('code', JSONResponse::localized('incorrectOrExpiredCode')) -> send();
}

$user = Auth::pendingTwoFactorUser();

if ($user === null) {
    JSONResponse::localizedError('thisAccountCanNoLongerLogIn', 403) -> send();
}

$remember_me = ($_SESSION['pending2FARememberMe'] ?? false) === true;

// The pending flags must go before Auth::login() regenerates the session,
// so a completed 2FA can never be replayed against the same pending state.
Auth::clearPendingTwoFactor();

Auth::login($user);
LoginFingerprint::record((int) $user -> userId);

if ($remember_me) {
    RememberToken::issue((int) $user -> userId);
}

JSONResponse::success(['loggedIn' => true]) -> send();
