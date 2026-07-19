<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Not configured (no admin-set client id/secret) - nothing to start.
if (!GoogleAuth::isEnabled()) {
    header('Location: ' . ServerURL::absolute('/login'));
    exit;
}

// intent=delete re-verifies a logged-in account's Google identity so it can be
// deleted (a Google account has no usable password to confirm with - see
// DeleteAccountForm). Every other start is an ordinary sign-in, which only
// makes sense when logged out.
$intent = ($_GET['intent'] ?? '') === 'delete' ? 'delete' : null;

if ($intent === 'delete') {
    if (!Auth::check()) {
        header('Location: ' . ServerURL::absolute('/login'));
        exit;
    }
} elseif (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

// A CSRF state and a replay-guard nonce, kept in the session and verified when
// Google redirects back. The intent rides alongside them so the callback knows
// whether this round trip is a sign-in or a delete confirmation.
$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));
$_SESSION['googleOauthState'] = $state;
$_SESSION['googleOauthNonce'] = $nonce;
$_SESSION['googleOauthIntent'] = $intent;

header('Location: ' . GoogleAuth::authorizeURL($state, $nonce));
exit;
