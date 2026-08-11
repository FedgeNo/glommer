<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

$page = new Page(['title' => (string) (Strings::for('PageTitle')['bookmarks'] ?? ''), 'needsMath' => true]);

$page -> addContent(new BookmarkList(['userId' => (int) $current_user -> userId]));

$page -> send();
