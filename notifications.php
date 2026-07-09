<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

['rows' => $rows, 'hasMore' => $has_more] = Notification::rowsForUser($current_user -> userId, 20);

// Marks everything seen before the nav renders, so its "unseen" dot reflects
// this visit immediately rather than on the next page load.
Notification::markSeen($current_user -> userId);
Auth::clearUserCache();

$page = Page::create('Notifications');

$page -> addContents(NotificationList::fromRows($rows, $has_more));

$page -> send();
