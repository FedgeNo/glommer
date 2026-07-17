<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$page = new Page(['title' => 'Forgot Password']);
$page -> addContent(new ForgotPasswordForm());
$page -> send();
