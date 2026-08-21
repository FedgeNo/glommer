<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if (Auth::check()) {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

$token = (string) ($_GET['token'] ?? '');

if ($token === '' || PasswordReset::verify($token) === null) {
    $page = new Page(['title' => (string) (Strings::for('PageTitle')['resetPassword'] ?? '')]);

    $page -> addContent(new Paragraph((string) (Strings::for('RouteNotices')['passwordResetInvalid'] ?? '')));

    $page -> send();
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['resetPassword'] ?? '')]);
$page -> addContent(new PasswordResetForm($token));
$page -> send();
