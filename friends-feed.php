<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

['rows' => $feed_rows, 'hasMore' => $has_more] = Timeline::rowsForUser((int) $current_user -> userId, 20);

$page = Page::create('Friends Feed', needsMath: true);

$page -> addContents(FeedList::fromRows('friends', $feed_rows, $has_more));

$page -> send();
