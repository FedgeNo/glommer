<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

if (!Auth::canModerate()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['adminBanned'] ?? '')]);

$page -> addContent(new BannedUserSearch());
$page -> addContent(new BannedUserSection());

$page -> send();
