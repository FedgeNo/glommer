<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . URL::absolute('/'));
    exit;
}

$errors = [];
$rate_key = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (RateLimiter::tooManyAttempts($rate_key, 10, 900)) {
        $errors[] = 'Too many login attempts. Please try again later.';
    } else {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $captcha_token = is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : null;

        // A second limit keyed on the account, not the address - the IP limit
        // alone never trips for a guessing attack spread across many machines
        // that all target one account.
        $account_rate_key = 'login-user:' . strtolower($identifier);

        if ($identifier === '' || $password === '') {
            $errors[] = 'Username/email and password are required.';
        } elseif (RateLimiter::tooManyAttempts($account_rate_key, 10, 900)) {
            $errors[] = 'Too many login attempts for this account. Please try again later.';
        } elseif (!Turnstile::verify($captcha_token, $_SERVER['REMOTE_ADDR'] ?? null, fail_open_on_error: true)) {
            // A no-op when Turnstile isn't configured. Fail open on a Cloudflare
            // outage: a definite bad/absent token is still rejected, but an
            // unreachable Cloudflare mustn't lock every user out of an account
            // they can already prove with a password (which is rate-limited too).
            $errors[] = 'Captcha verification failed. Please try again.';
        } else {
            $user = Auth::attempt($identifier, $password);

            if ($user === null) {
                // Only failed attempts count toward the limits, so legitimate
                // logins never eat into them.
                RateLimiter::recordAttempt($rate_key);
                RateLimiter::recordAttempt($account_rate_key);

                $errors[] = 'Incorrect username/email or password.';
            } else {
                if (($_POST['rememberMe'] ?? '') === '1') {
                    RememberToken::issue((int) $user -> userId);
                }

                header('Location: ' . URL::absolute('/'));
                exit;
            }
        }
    }
}

$page = Page::create('Log In');

if ($errors !== []) {
    $page -> addContents(new ErrorList($errors));
}

$page -> addContents(new LoginForm());

$page -> addContents(new Anchor(URL::absolute('/forgot-password'), 'Forgot password?'));

$page -> addContents(new Anchor(URL::absolute('/signup'), 'Need an account? Sign up'));

$page -> send();
