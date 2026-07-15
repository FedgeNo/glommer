<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$page = Page::create('Log In');

if (GoogleAuth::isEnabled()) {
    $page -> addContent(new GoogleSignInButton());
    $page -> addContent(new AuthDivider());
}

$page -> addContent(new LoginForm());

$page -> addContent(new Anchor(ServerURL::absolute('/forgot-password'), 'Forgot password?'));

$page -> addContent(new Anchor(ServerURL::absolute('/signup'), 'Need an account? Sign up'));

$page -> send();
