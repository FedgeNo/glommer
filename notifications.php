<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

// Marks everything seen before the nav renders, so its "unseen" dot reflects
// this visit immediately rather than on the next page load.
Notification::markSeen($current_user -> userId);
Auth::clearUserCache();

$page = Page::create('Notifications');

$page -> addContent(new NotificationList(['userId' => (int) $current_user -> userId]));

$page -> send();
