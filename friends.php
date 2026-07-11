<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// The friends page now lives per-user at /users/{username}/friends (public,
// with your request sections shown only on your own). /friends/ stays as the
// "my friends" shortcut - it just sends you to your own page there.
Auth::requireLogin();

header('Location: ' . URL::absolute('/users/' . Auth::user() -> username . '/friends'));
exit;
