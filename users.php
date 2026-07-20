<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Users']);
$p = new Paragraph('You can follow Fediverse users in ');
$a = new Anchor(ServerURL::absolute('/settings'), 'Settings');
$p -> addContent($a);
$page -> addContent($p);
$page -> addContent(new UserSearch(['viewerId' => (int) Auth::user() -> userId]));
$page -> send();
