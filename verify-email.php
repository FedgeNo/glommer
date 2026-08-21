<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// A bare GET only ever shows a confirmation button; the token is consumed by
// the POST that button makes (carrying the CSRF token init.php verifies). The
// verification link is fetched automatically by email security scanners
// (SafeLinks, Mimecast, Gmail prefetch), so a GET-side verify would let a
// blind scanner consume the token before the user opened the message.
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');

$page = new Page(['title' => (string) (Strings::for('PageTitle')['verifyEmail'] ?? '')]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $token !== '' ? EmailVerification::verify($token) : null;

    $words = Strings::for('VerifyEmailPage');
    $message = $user_id !== null
        ? str_replace('{site}', (string) Config::get('siteTitle'), (string) ($words['verified'] ?? ''))
        : (string) ($words['invalid'] ?? '');
    $page -> addContent(new Paragraph($message));
    $page -> addContent(new Anchor(ServerURL::absolute('/'), (string) ($words['continue'] ?? '')));

    $page -> send();
    exit;
}

if ($token === '') {
    $page -> addContent(new Paragraph((string) (Strings::for('VerifyEmailPage')['invalid'] ?? '')));
    $page -> send();
    exit;
}

$page -> addContent(new Paragraph((string) (Strings::for('VerifyEmailPage')['confirm'] ?? '')));
$page -> addContent(new EmailVerifyForm($token));

$page -> send();
