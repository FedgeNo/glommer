<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Logout must be a CSRF-protected POST (init.php verifies the token on every
// POST) - a GET would let a third-party page force-log-out a victim with
// something as simple as an <img> tag. A GET here just bounces home without
// touching the session.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ServerURL::absolute('/'));
    exit;
}

RememberToken::forget();
Auth::logout();

header('Location: ' . ServerURL::absolute('/'));
exit;
