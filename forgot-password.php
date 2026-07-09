<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . URL::absolute('/'));
    exit;
}

$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $mysqli = Database::connection();
    $rate_key = 'forgot-password:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    if (RateLimiter::tooManyAttempts($rate_key, 5, 900)) {
        $errors[] = 'Too many password reset requests. Please try again later.';
    } else {
        if ($email !== '') {
            RateLimiter::recordAttempt($rate_key);

            $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE `email` = ?
');
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_object($result, User::class);

            if ($user !== null) {
                PasswordReset::sendFor($user);
            }
        }

        // Always show the same message regardless of whether the email matched, to avoid leaking which emails have accounts.
        $sent = true;
    }
}

$page = Page::create('Forgot Password');

if ($sent) {
    $page -> addContents(new Paragraph('If that email address is on file, a password reset link has been sent.'));
} else {
    if ($errors !== []) {
        $page -> addContents(new ErrorList($errors));
    }

    $page -> addContents(new ForgotPasswordForm());
}

$page -> send();
