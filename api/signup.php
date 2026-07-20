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

$username = substr(preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($payload['username'] ?? '')))), 0, User::MAX_USERNAME_LENGTH);
$email = trim((string) ($payload['email'] ?? ''));
$password = (string) ($payload['password'] ?? '');
$display_name = mb_substr(trim((string) ($payload['displayName'] ?? '')), 0, 50);
$description = mb_substr(trim((string) ($payload['description'] ?? '')), 0, 500);
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

$mysqli = DB::connection();
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
    $stmt = DB::run('
SELECT `userId`
    FROM `Users`
    WHERE `slug` = ? OR `email` = ?
', 'ss', $username, $email);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0 || EmailChangeRevert::isReserved($email)) {
        $errors[] = 'That username or email is already taken.';
    }
}

if ($errors !== []) {
    JSONResponse::error(implode(' ', $errors), 422) -> send();
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$description_value = $description !== '' ? $description : null;

$unverified = 0;

DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `description`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'sssssi', $username, $email, $hash, $display_name, $description_value, $unverified);
$new_user_id = (int) mysqli_insert_id($mysqli);

$user = new User();
$user -> userId = $new_user_id;
$user -> slug = $username;
$user -> email = $email;
$user -> title = $display_name;
$user -> description = $description_value;
$user -> verified = $unverified;

Auth::login($user);
LoginFingerprint::record($new_user_id);

if (($payload['rememberMe'] ?? false) === true) {
    RememberToken::issue($new_user_id);
}

$auto_verified = EmailVerification::sendFor($user);

JSONResponse::success(['signedUp' => true, 'verified' => $auto_verified]) -> send();
