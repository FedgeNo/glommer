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

        if ($identifier === '' || $password === '') {
            $errors[] = 'Username/email and password are required.';
        } else {
            $user = Auth::attempt($identifier, $password);

            if ($user === null) {
                // Only failed attempts count toward the limit, so legitimate
                // logins never eat into it.
                RateLimiter::recordAttempt($rate_key);

                $errors[] = 'Incorrect username/email or password.';
            } else {
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

$page -> addContents(new Anchor(URL::absolute('/forgot-password/'), 'Forgot password?'));

$page -> addContents(new Anchor(URL::absolute('/signup/'), 'Need an account? Sign up'));

$page -> send();
