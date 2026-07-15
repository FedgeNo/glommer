<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$username = substr(preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($payload['username'] ?? '')))), 0, 32);
$email = trim((string) ($payload['email'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$display_name = mb_substr(trim((string) ($payload['displayName'] ?? '')), 0, 100);
$captcha_token = is_string($payload['captchaToken'] ?? null) ? $payload['captchaToken'] : null;

$errors = [];

if ($username === '') {
    $errors[] = 'Username is required.';
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'A valid email is required.';
}

if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
} elseif (strlen($password) > 72) {
    // bcrypt (password_hash's default) only uses the first 72 bytes and rejects longer input outright.
    $errors[] = 'Password must be at most 72 characters.';
}

$mysqli = Database::connection();
$rate_key = 'signup:' . (ServerURL::clientIP() ?? 'unknown');

if ($errors === []) {
    if (RateLimiter::tooManyAttempts($rate_key, 5, 3600)) {
        $errors[] = 'Too many signups from your network. Please try again later.';
    } else {
        // Count every well-formed attempt, not just a successful signup -
        // otherwise a probe for which emails already exist (via the
        // "already taken" response) never counts toward the limit and
        // account enumeration is unthrottled.
        RateLimiter::recordAttempt($rate_key);
    }
}

// Verify the CAPTCHA server-side (after the cheap rate-limit check, so a
// flood doesn't make us hammer Cloudflare) - a no-op when it isn't
// configured. Fail closed: if Cloudflare can't be reached, reject rather
// than open a bot window on sign-up.
if ($errors === [] && !Turnstile::verify($captcha_token, ServerURL::clientIP())) {
    $errors[] = 'Captcha verification failed. Please try again.';
}

if ($errors === []) {
    $stmt = mysqli_prepare($mysqli, '
SELECT `userId`
    FROM `Users`
    WHERE `username` = ? OR `email` = ?
');
    mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0 || EmailChangeRevert::isReserved($email)) {
        $errors[] = 'That username or email is already taken.';
    }
}

if ($errors !== []) {
    JSONResponse::error(implode(' ', $errors), 422) -> send();
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$display_name_value = $display_name !== '' ? $display_name : null;

$unverified = 0;

$stmt = mysqli_prepare($mysqli, '
INSERT INTO `Users` (`username`, `email`, `passwordHash`, `displayName`, `verified`)
    VALUES (?, ?, ?, ?, ?)
');
mysqli_stmt_bind_param($stmt, 'ssssi', $username, $email, $hash, $display_name_value, $unverified);
mysqli_stmt_execute($stmt);
$new_user_id = (int) mysqli_insert_id($mysqli);

$user = new User();
$user -> userId = $new_user_id;
$user -> username = $username;
$user -> email = $email;
$user -> displayName = $display_name_value;
$user -> verified = $unverified;

Auth::login($user);

if (($payload['rememberMe'] ?? false) === true) {
    RememberToken::issue($new_user_id);
}

$auto_verified = EmailVerification::sendFor($user);

JSONResponse::success(['signedUp' => true, 'verified' => $auto_verified]) -> send();
