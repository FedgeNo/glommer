<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$token = (string) ($_GET['token'] ?? '');

if ($token === '' || PasswordReset::verify($token) === null) {
    $page = new Page(['title' => 'Reset Password']);

    $page -> addContent(new Paragraph('That password reset link is invalid or has expired.'));

    $page -> send();
    exit;
}

$page = new Page(['title' => 'Reset Password']);
$page -> addContent(new ResetPasswordForm($token));
$page -> send();
