<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$token = (string) ($_GET['token'] ?? '');

$user_id = $token !== '' ? EmailVerification::verify($token) : null;

$page = new Page(['title' => 'Verify Email']);

$page -> addContent(new Paragraph($user_id !== null
    ? 'Your email has been verified. You can now use Glommer.'
    : 'That verification link is invalid or has expired.'));

$page -> addContent(new Anchor(ServerURL::absolute('/'), 'Continue'));

$page -> send();
