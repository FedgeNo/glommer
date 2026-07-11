<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();

$incoming = $current_user -> getIncomingFriendRequests();
$outgoing = $current_user -> getOutgoingFriendRequests();

$page = Page::create('Friends');

if ($incoming !== []) {
    $page -> addContents(PendingFriendRequestList::withItems($incoming));
}

$page -> addContents(FriendList::withItems($current_user -> getFriends()));

if ($outgoing !== []) {
    $page -> addContents(OutgoingFriendRequestList::withItems($outgoing));
}

$page -> send();
