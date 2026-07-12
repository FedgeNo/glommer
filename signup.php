<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = substr(preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($_POST['username'] ?? '')))), 0, 32);
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $display_name = mb_substr(trim((string) ($_POST['displayName'] ?? '')), 0, 100);
    $captcha_token = is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : null;

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

    if ($errors === [] && RateLimiter::tooManyAttempts($rate_key, 5, 3600)) {
        $errors[] = 'Too many signups from your network. Please try again later.';
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

    if ($errors === []) {
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

        RateLimiter::recordAttempt($rate_key);

        $user = new User();
        $user -> userId = $new_user_id;
        $user -> username = $username;
        $user -> email = $email;
        $user -> displayName = $display_name_value;
        $user -> verified = $unverified;

        Auth::login($user);

        if (($_POST['rememberMe'] ?? '') === '1') {
            RememberToken::issue($new_user_id);
        }

        EmailVerification::sendFor($user);

        header('Location: ' . ServerURL::absolute('/check-inbox'));
        exit;
    }
}

$page = Page::create('Sign Up');

if ($errors !== []) {
    $page -> addContents(new ErrorList($errors));
}

$page -> addContents(new SignupForm());

$page -> send();
