<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Auth::requireLogin();
APIToken::rotate((int) Auth::user() -> userId);

header('Location: /user-settings', true, 303);
exit;
