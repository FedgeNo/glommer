<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// No login required - the token itself is the proof of authorization (same
// as password reset), since the whole point is recovering an account whose
// current email may belong to whoever changed it.
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');

$page = new Page(['title' => (string) (Strings::for('PageTitle')['revertEmail'] ?? '')]);

// Only a deliberate POST (carrying the CSRF token init.php verifies) reverts.
// A GET renders a confirmation button instead: the revert link is mailed to
// the account's pre-change address, and email security scanners (SafeLinks,
// Mimecast, Gmail prefetch) fetch every link automatically, so a GET-side
// revert would let a blind scanner fetch silently undo a legitimate change
// and sign the user out of every device.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $words = Strings::for('RevertEmailPage');

    if ($token !== '' && EmailChangeRevert::consume($token)) {
        $page -> addContent(new Paragraph((string) ($words['reverted'] ?? '')));
        $page -> addContent(new Paragraph((string) ($words['securityAdvice'] ?? '')));
        $page -> addContent(new Anchor(ServerURL::absolute('/login'), (string) ($words['login'] ?? '')));
        $page -> addContent(new Anchor(ServerURL::absolute('/forgot-password'), (string) ($words['forgotPassword'] ?? '')));
    } else {
        $page -> addContent(new Paragraph((string) ($words['invalid'] ?? '')));
    }

    $page -> send();
    exit;
}

if ($token === '') {
    $page -> addContent(new Paragraph((string) (Strings::for('RevertEmailPage')['invalid'] ?? '')));
    $page -> send();
    exit;
}

$page -> addContent(new Paragraph((string) (Strings::for('RevertEmailPage')['confirm'] ?? '')));
$page -> addContent(new EmailRevertForm($token));

$page -> send();
