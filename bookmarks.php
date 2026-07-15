<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

$bookmarks = Bookmark::rowsForUser((int) $current_user -> userId, 20);

$page = Page::create('Bookmarks', needsMath: true);

$page -> addContent(BookmarkList::fromRows(
    $bookmarks['rows'],
    $bookmarks['hasMore'],
    $bookmarks['oldestCreatedAt'],
    $bookmarks['oldestPostId']
));

$page -> send();
