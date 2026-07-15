<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

$saved = Bookmark::rowsForUser((int) $current_user -> userId, 20);

$page = Page::create('Saved Posts', needsMath: true);

$page -> addContent(SavedPostsList::fromRows(
    $saved['rows'],
    $saved['hasMore'],
    $saved['oldestCreatedAt'],
    $saved['oldestPostId']
));

$page -> send();
