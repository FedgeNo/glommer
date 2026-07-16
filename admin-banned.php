<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

if (!Auth::canModerate()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = Page::create('Banned Users');

// Linked at the top, above the infinite-scroll list, so the trending-entities
// view stays reachable without scrolling to the end of the banned users.
$page -> addContent(new Anchor(ServerURL::absolute('/admin/banned-entities'), 'Banned trending entities'));
$page -> addContent(new BannedUserSearch());
$page -> addContent(BannedUserList::page());

$page -> send();
