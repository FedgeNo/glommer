<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

$page = Page::create('Friends Feed', needsMath: true);

$page -> addContent(new FeedList(['feedType' => 'friends', 'userId' => (int) $current_user -> userId]));

$page -> send();
