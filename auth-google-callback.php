<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// intent=delete (set by auth-google.php) re-verifies a logged-in account's
// Google identity so it can be deleted - a Google account has no usable
// password to confirm with. Every other return is an ordinary sign-in, which
// only makes sense logged out (handled below).
$intent = $_SESSION['googleOauthIntent'] ?? null;
unset($_SESSION['googleOauthIntent']);

if ($intent === 'delete') {
    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    $session_state = $_SESSION['googleOauthState'] ?? null;
    $session_nonce = (string) ($_SESSION['googleOauthNonce'] ?? '');
    unset($_SESSION['googleOauthState'], $_SESSION['googleOauthNonce']);

    // The session ended between starting the flow and returning - there's no
    // logged-in account to delete.
    if (!Auth::check()) {
        header('Location: ' . ServerURL::absolute('/login'));
        exit;
    }

    $current_user = Auth::user();
    $error = null;

    if ((int) $current_user -> userId === 1) {
        // The site needs at least one admin - the same immunity
        // api/delete-account.php gives userId 1.
        $error = (string) (Strings::for('GoogleAuthCallbackPage')['accountCannotBeDeleted'] ?? '');
    } elseif (!GoogleAuth::isEnabled()) {
        $error = (string) (Strings::for('GoogleAuthCallbackPage')['notConfigured'] ?? '');
    } elseif (isset($_GET['error'])) {
        $error = (string) (Strings::for('GoogleAuthCallbackPage')['deletionCancelled'] ?? '');
    } elseif ($code === '' || !is_string($session_state) || $session_state === '' || !hash_equals($session_state, $state)) {
        $error = (string) (Strings::for('GoogleAuthCallbackPage')['deletionFailed'] ?? '');
    } else {
        $id_token = GoogleAuth::exchangeCodeForIdToken($code);
        $profile = $id_token !== null ? GoogleAuth::verifiedProfile($id_token, $session_nonce) : null;

        if ($profile === null) {
            $error = (string) (Strings::for('GoogleAuthCallbackPage')['identityFailed'] ?? '');
        } elseif (strcasecmp($profile['email'], (string) $current_user -> email) !== 0) {
            // The Google account signed into doesn't own this account's email,
            // so it's no proof of control over this account - refuse to delete.
            $error = (string) (Strings::for('GoogleAuthCallbackPage')['emailMismatch'] ?? '');
        } else {
            User::delete((int) $current_user -> userId);
            Auth::logout();
            RememberToken::forget();

            $page = new Page(['title' => (string) (Strings::for('PageTitle')['authGoogleCallbackAccountDeleted'] ?? '')]);
            $words = Strings::for('GoogleAuthCallbackPage');
            $page -> addContent(new Paragraph((string) ($words['deleted'] ?? '')));
            $page -> addContent(new Anchor(ServerURL::absolute('/'), (string) ($words['home'] ?? '')));
            $page -> send();
            exit;
        }
    }

    $page = new Page(['title' => (string) (Strings::for('PageTitle')['authGoogleCallbackDeleteAccount'] ?? '')]);
    $page -> addContent(new ErrorList([$error]));
    $page -> addContent(new Anchor(ServerURL::absolute('/user-settings'), (string) (Strings::for('GoogleAuthCallbackPage')['userSettings'] ?? '')));
    $page -> send();
    exit;
}

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$error = null;

if (!GoogleAuth::isEnabled()) {
    $error = (string) (Strings::for('GoogleAuthCallbackPage')['notConfigured'] ?? '');
} elseif (isset($_GET['error'])) {
    // The user declined consent, or Google returned an error.
    $error = (string) (Strings::for('GoogleAuthCallbackPage')['signinCancelled'] ?? '');
} else {
    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    $session_state = $_SESSION['googleOauthState'] ?? null;
    $session_nonce = (string) ($_SESSION['googleOauthNonce'] ?? '');

    // Single-use: consume the stored state/nonce whatever the outcome.
    unset($_SESSION['googleOauthState'], $_SESSION['googleOauthNonce']);

    if ($code === '' || !is_string($session_state) || $session_state === '' || !hash_equals($session_state, $state)) {
        // Missing code, or a state mismatch (CSRF, or a stale/replayed callback).
        $error = (string) (Strings::for('GoogleAuthCallbackPage')['signinFailed'] ?? '');
    } else {
        // The flow is already gated by a real Google login, but cap
        // account-creation bursts from one address. Every processed callback
        // counts toward the cap, so neither repeated verification failures nor
        // account-creation bursts from one address go unthrottled.
        $rate_key = 'google-auth:' . (ServerURL::clientIP() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($rate_key, 10, 3600)) {
            $error = (string) (Strings::for('GoogleAuthCallbackPage')['rateLimited'] ?? '');
        } else {
            RateLimiter::recordAttempt($rate_key);

            $id_token = GoogleAuth::exchangeCodeForIdToken($code);
            $profile = $id_token !== null ? GoogleAuth::verifiedProfile($id_token, $session_nonce) : null;

            if ($profile === null) {
                $error = (string) (Strings::for('GoogleAuthCallbackPage')['verificationFailed'] ?? '');
            } elseif (($user = GoogleAuth::resolveUser($profile['email'], $profile['name'])) === null) {
                // The email is reserved for someone else's outstanding
                // email-change revert, so no account can be made for it here.
                $error = (string) (Strings::for('GoogleAuthCallbackPage')['emailUnavailable'] ?? '');
            } elseif ($user -> banned) {
                $words = Strings::for('GoogleAuthCallbackPage');
                $error = $user -> banReason !== null && $user -> banReason !== ''
                    ? str_replace('{reason}', $user -> banReason, (string) ($words['bannedWithReason'] ?? ''))
                    : (string) ($words['banned'] ?? '');
            } else {
                if (TwoFactor::isEnabled($user)) {
                    // Opt-in email 2FA applies the same way it does to a
                    // password login (api/login.php) - a verified Google
                    // identity alone isn't enough to skip a second factor the
                    // user turned on. Hands off to login.php's pending-state
                    // code-entry step, same one api/login.php starts, and
                    // fails closed the same way: a broken mailer means the
                    // code step asks for a recovery code instead.
                    $code_sent = TwoFactor::sendCode($user);
                    $email_failed = !$code_sent && !Mailer::recipientWasRejected();

                    if ($email_failed) {
                        Notification::warnAdminMailerFailed((int) $user -> userId);
                    }

                    $_SESSION['pending2FAUserId'] = (int) $user -> userId;
                    // A Google sign-in implies "keep me signed in".
                    $_SESSION['pending2FARememberMe'] = true;
                    $_SESSION['pending2FAEmailFailed'] = $email_failed;

                    header('Location: ' . ServerURL::absolute('/login'));
                    exit;
                }

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

$page = new Page(['title' => (string) (Strings::for('PageTitle')['authGoogleCallbackLogin'] ?? '')]);
$page -> addContent(new ErrorList([$error]));
$page -> addContent(new Anchor(ServerURL::absolute('/login'), (string) (Strings::for('GoogleAuthCallbackPage')['signin'] ?? '')));
$page -> send();
