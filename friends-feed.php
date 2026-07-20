<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

$page = new Page(['title' => 'Friends Feed', 'needsMath' => true]);

$page -> addContent(new FriendsFeedList(['userId' => (int) $current_user -> userId]));

$page -> send();
