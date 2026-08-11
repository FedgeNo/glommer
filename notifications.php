<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

// Marks everything seen before the nav renders, so its "unseen" dot reflects
// this visit immediately rather than on the next page load.
Notification::markSeen($current_user -> userId);
Auth::clearUserCache();

// The live notification handler needs to know it is on this page before it
// builds a list over an empty state - every page carries the nav dropdown's.
$page = new Page(['title' => (string) (Strings::for('PageTitle')['notifications'] ?? ''), 'bodyClass' => 'NotificationsPage']);

$page -> addContent(new NotificationList(['userId' => (int) $current_user -> userId]));

$page -> send();
