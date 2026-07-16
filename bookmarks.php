<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

$page = Page::create('Bookmarks', needsMath: true);

$page -> addContent(new BookmarkList(['userId' => (int) $current_user -> userId]));

$page -> send();
