<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

if (!Auth::canModerate()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = Page::create('Banned Trending Entities');

$page -> addContent(new Anchor(ServerURL::absolute('/admin/banned'), 'Banned users'));
$page -> addContent(new BannedTrendingEntitiesList());

$page -> send();
