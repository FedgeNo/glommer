<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => (string) (Strings::for('PageTitle')['users'] ?? '')]);
$words = Strings::for('UsersPage');
$p = new Paragraph((string) ($words['fediverseBefore'] ?? ''));
$a = new Anchor(ServerURL::absolute('/user-settings'), (string) ($words['settingsLink'] ?? ''));
$p -> addContent($a);
$p -> addContent((string) ($words['fediverseAfter'] ?? ''));
$page -> addContent($p);
$page -> addContent(new UserSearch());
$page -> send();
