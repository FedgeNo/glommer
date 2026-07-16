<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$error = null;

if (!GoogleAuth::isEnabled()) {
    $error = 'Google sign-in is not configured.';
} elseif (isset($_GET['error'])) {
    // The user declined consent, or Google returned an error.
    $error = 'Google sign-in was cancelled.';
} else {
    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    $session_state = $_SESSION['googleOauthState'] ?? null;
    $session_nonce = (string) ($_SESSION['googleOauthNonce'] ?? '');

    // Single-use: consume the stored state/nonce whatever the outcome.
    unset($_SESSION['googleOauthState'], $_SESSION['googleOauthNonce']);

    if ($code === '' || !is_string($session_state) || $session_state === '' || !hash_equals($session_state, $state)) {
        // Missing code, or a state mismatch (CSRF, or a stale/replayed callback).
        $error = 'Google sign-in could not be completed. Please try again.';
    } else {
        // The flow is already gated by a real Google login, but cap
        // account-creation bursts from one address. Every processed callback
        // counts toward the cap, so neither repeated verification failures nor
        // account-creation bursts from one address go unthrottled.
        $rate_key = 'google-auth:' . (ServerURL::clientIP() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($rate_key, 10, 3600)) {
            $error = 'Too many sign-in attempts from your network. Please try again later.';
        } else {
            RateLimiter::recordAttempt($rate_key);

            $id_token = GoogleAuth::exchangeCodeForIdToken($code);
            $profile = $id_token !== null ? GoogleAuth::verifiedProfile($id_token, $session_nonce) : null;

            if ($profile === null) {
                $error = 'Google sign-in could not be verified. Please try again.';
            } elseif (($user = GoogleAuth::resolveUser($profile['email'], $profile['name'])) === null) {
                // The email is reserved for someone else's outstanding
                // email-change revert, so no account can be made for it here.
                $error = 'This email address is not available for sign-in.';
            } elseif ($user -> banned) {
                $error = $user -> banReason !== null && $user -> banReason !== ''
                    ? 'Your account has been banned. Reason: ' . $user -> banReason
                    : 'Your account has been banned.';
            } else {
                Auth::login($user);
                LoginFingerprint::record((int) $user -> userId);
                // A Google sign-in implies "keep me signed in".
                RememberToken::issue((int) $user -> userId);

                header('Location: ' . ServerURL::absolute('/'));
                exit;
            }
        }
    }
}

$page = Page::create('Log In');
$page -> addContent(new ErrorList([$error]));
$page -> addContent(new Anchor(ServerURL::absolute('/login'), 'Back to sign in'));
$page -> send();
