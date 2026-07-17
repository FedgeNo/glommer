<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$page = new Page(['title' => 'Sign Up']);

if (GoogleAuth::isEnabled()) {
    $page -> addContent(new GoogleSignInButton());
    $page -> addContent(new AuthDivider());
}

$page -> addContent(new SignupForm());

$page -> send();
