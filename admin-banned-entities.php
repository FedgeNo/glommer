<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

if (!Auth::canModerate()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => 'Banned Trending Entities']);

$page -> addContent(new BannedTrendingEntitiesList());

$page -> send();
