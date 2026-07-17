<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

// "Start over" from the code step abandons the in-progress 2FA login and
// returns to the password form. Clearing a pending (not-yet-completed) login
// state is harmless - worst case it just forces a fresh login - so a plain
// GET is fine here; there's no logged-in state to protect.
if (isset($_GET['restart'])) {
    unset($_SESSION['pending2FAUserId'], $_SESSION['pending2FARememberMe']);

    header('Location: ' . ServerURL::absolute('/login'));
    exit;
}

// Mid-2FA (password already verified by api/login.php, code emailed): show the
// code-entry step instead of the password form, so a refresh here doesn't drop
// the user back to re-entering their password.
if (isset($_SESSION['pending2FAUserId'])) {
    $page = new Page(['title' => 'Verification Code']);
    $page -> addContent(new TwoFactorForm());
    $page -> addContent(new Anchor(ServerURL::absolute('/login?restart=1'), 'Start over'));
    $page -> send();
    exit;
}

$page = new Page(['title' => 'Log In']);

if (GoogleAuth::isEnabled()) {
    $page -> addContent(new GoogleSignInButton());
    $page -> addContent(new AuthDivider());
}

$page -> addContent(new LoginForm());

$page -> addContent(new Anchor(ServerURL::absolute('/forgot-password'), 'Forgot password?'));

$page -> addContent(new Anchor(ServerURL::absolute('/signup'), 'Need an account? Sign up'));

$page -> send();
