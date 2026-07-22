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

$rate_key = 'login:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 10, 900)) {
    JSONResponse::error('Too many login attempts. Please try again later.', 429) -> send();
}

$identifier = trim((string) ($payload['identifier'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$captcha_token = is_string($payload['captchaToken'] ?? null) ? $payload['captchaToken'] : null;
$recaptcha_token = is_string($payload['recaptchaToken'] ?? null) ? $payload['recaptchaToken'] : null;

// A second limit keyed on the account, not the address - the IP limit alone
// never trips for a guessing attack spread across many machines that all
// target one account. Truncated to fit RateLimitAttempts.rateKey's
// varchar(255) - an oversized identifier would otherwise make the INSERT
// throw a data-truncation error (a 500) instead of counting the attempt.
$account_rate_key = substr('login-user:' . strtolower($identifier), 0, 255);

if ($identifier === '' || $password === '') {
    JSONResponse::error('Username/email and password are required.', 422) -> send();
}

if (RateLimiter::tooManyAttempts($account_rate_key, 10, 900)) {
    // This account is under its per-account lockout. Hard-blocking here is a
    // targeted-DoS foot-gun: anyone can lock a known account out for the whole
    // window with a handful of wrong passwords, from any address. When reCAPTCHA
    // is configured, let a solved challenge carry a single attempt through
    // instead - a human proving themselves per attempt throttles automated
    // guessing to human speed without stranding the real owner. With no
    // reCAPTCHA set up there's nothing to prove humanity with, so it stays a
    // hard block rather than leave the account open to unthrottled guessing.
    if (!ReCaptcha::isEnabled()) {
        JSONResponse::error('Too many login attempts for this account. Please try again later.', 429) -> send();
    }

    // Missing/unsolved token, or Google unreachable (verify fails closed): tell
    // the client to show the challenge and try again. A success envelope, not an
    // error, so the client reads the flag rather than surfacing a toast - the
    // same shape the 2FA step uses.
    if (!ReCaptcha::verify($recaptcha_token, ServerURL::clientIP())) {
        JSONResponse::success([
            'recaptchaRequired' => true,
            'recaptchaSiteKey' => ReCaptcha::siteKey(),
        ]) -> send();
    }
}

// A no-op when Turnstile isn't configured. Fail open on a Cloudflare outage:
// a definite bad/absent token is still rejected, but an unreachable
// Cloudflare mustn't lock every user out of an account they can already
// prove with a password (which is rate-limited too).
if (!Turnstile::verify($captcha_token, ServerURL::clientIP(), fail_open_on_error: true)) {
    JSONResponse::error('Captcha verification failed. Please try again.', 422) -> send();
}

$user = Auth::verifyCredentials($identifier, $password);

if ($user === null) {
    // Only failed attempts count toward the limits, so legitimate logins
    // never eat into them.
    RateLimiter::recordAttempt($rate_key);
    RateLimiter::recordAttempt($account_rate_key);

    JSONResponse::error('Incorrect username/email or password.', 422) -> send();
}

if ($user -> banned) {
    // Correct password but banned: show the reason a moderator gave, rather
    // than logging them in.
    $message = $user -> banReason !== null && $user -> banReason !== ''
        ? 'Your account has been banned. Reason: ' . $user -> banReason
        : 'Your account has been banned.';

    JSONResponse::error($message, 403) -> send();
}

$remember_me = ($payload['rememberMe'] ?? false) === true;

// Opt-in email 2FA: the password was right, but don't log in yet - email a
// code and hand off to api/verify-2fa.php, carrying who's mid-login (and
// whether they asked to be remembered) in the session, NOT a logged-in
// state. The one exception is a broken site mailer: if the code can't be
// sent because mail delivery itself is down (not this user's address being
// rejected), fall through to a normal login rather than locking every 2FA
// user out of their own account - same fallback EmailVerification uses.
if (TwoFactor::isEnabled($user)) {
    $code_sent = TwoFactor::sendCode($user);

    if ($code_sent || Mailer::recipientWasRejected()) {
        $_SESSION['pending2FAUserId'] = (int) $user -> userId;
        $_SESSION['pending2FARememberMe'] = $remember_me;

        JSONResponse::success(['twoFactorRequired' => true]) -> send();
    }

    // Mailer itself is down - can't enforce 2FA. Let them in and tell the
    // admin their mail is broken (throttled inside the notification).
    Notification::warnAdminMailerFailed((int) $user -> userId);
}

Auth::login($user);
LoginFingerprint::record((int) $user -> userId);

if ($remember_me) {
    RememberToken::issue((int) $user -> userId);
}

JSONResponse::success(['loggedIn' => true]) -> send();
