<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . URL::absolute('/'));
    exit;
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if ($token === '' || PasswordReset::verify($token) === null) {
    $page = Page::create('Reset Password');

    $page -> addContents(new Paragraph('That password reset link is invalid or has expired.'));

    $page -> send();
    exit;
}

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = (string) ($_POST['newPassword'] ?? '');
    $confirm_password = (string) ($_POST['confirmPassword'] ?? '');

    if (strlen($new_password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($new_password) > 72) {
        // bcrypt (password_hash's default) only uses the first 72 bytes and rejects longer input outright.
        $errors[] = 'Password must be at most 72 characters.';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    } else {
        PasswordReset::consume($token, $new_password);
        $done = true;
    }
}

$page = Page::create('Reset Password');

if ($done) {
    $page -> addContents(new Paragraph('Your password has been reset. You can now log in.'));
    $page -> addContents(new Anchor(URL::absolute('/login/'), 'Log In'));
} else {
    if ($errors !== []) {
        $page -> addContents(new ErrorList($errors));
    }

    $page -> addContents(new ResetPasswordForm($token));
}

$page -> send();
