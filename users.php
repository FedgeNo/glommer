<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Users']);
$p = new Paragraph('You can follow a list of Fediverse users in ');
$a = new Anchor(ServerURL::absolute('/user-settings'), 'User Settings');
$p -> addContent($a);
$page -> addContent($p);
$page -> addContent(new UserSearch());
$page -> send();
