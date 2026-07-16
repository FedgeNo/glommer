<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

if (!Auth::canModerate()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = Page::create('Banned Users');

$page -> addContent(new Heading2('Banned Users'));
$page -> addContent(new BannedUserSearch());
$page -> addContent(BannedUserList::page());

$page -> addContent(new Heading2('Banned Trending Entities'));
$page -> addContent(new BannedTrendingEntitiesList());

$page -> send();
