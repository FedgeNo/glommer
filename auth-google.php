<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

// Not configured (no admin-set client id/secret) - nothing to start.
if (!GoogleAuth::isEnabled()) {
    header('Location: ' . ServerURL::absolute('/login'));
    exit;
}

// A CSRF state and a replay-guard nonce, kept in the session and verified when
// Google redirects back.
$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));
$_SESSION['googleOauthState'] = $state;
$_SESSION['googleOauthNonce'] = $nonce;

header('Location: ' . GoogleAuth::authorizeURL($state, $nonce));
exit;
