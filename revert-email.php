<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// No login required - the token itself is the proof of authorization (same
// as password reset), since the whole point is recovering an account whose
// current email may belong to whoever changed it.
$token = (string) ($_GET['token'] ?? '');
$reverted = $token !== '' && EmailChangeRevert::consume($token);

$page = Page::create('Revert Email Change');

if ($reverted) {
    $page -> addContents(new Paragraph('Your email address has been reverted and every device has been signed out of your account.'));
    $page -> addContents(new Paragraph('If you\'re not sure how this happened, change your password as soon as you log back in.'));
    $page -> addContents(new Anchor(ServerURL::absolute('/login'), 'Log In'));
    $page -> addContents(new Anchor(ServerURL::absolute('/forgot-password'), 'Forgot password?'));
} else {
    $page -> addContents(new Paragraph('That revert link is invalid or has expired.'));
}

$page -> send();
