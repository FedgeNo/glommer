<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();
$mysqli = Database::connection();

$pending_status = 'pending';
$accepted_status = 'accepted';

$incoming_stmt = mysqli_prepare($mysqli, '
SELECT `f`.`friendshipId`, `u`.`userId`, `u`.`username`, `u`.`displayName`, `u`.`hasAvatar`, `u`.`createdAt`
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ?
');
mysqli_stmt_bind_param($incoming_stmt, 'is', $current_user -> userId, $pending_status);
mysqli_stmt_execute($incoming_stmt);
$incoming_result = mysqli_stmt_get_result($incoming_stmt);

$incoming_rows = [];

while ($row = mysqli_fetch_assoc($incoming_result)) {
    $incoming_rows[] = $row;
}

$outgoing_stmt = mysqli_prepare($mysqli, '
SELECT `u`.`userId`, `u`.`username`, `u`.`displayName`, `u`.`hasAvatar`, `u`.`createdAt`
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ?
');
mysqli_stmt_bind_param($outgoing_stmt, 'is', $current_user -> userId, $pending_status);
mysqli_stmt_execute($outgoing_stmt);
$outgoing_result = mysqli_stmt_get_result($outgoing_stmt);

$outgoing_rows = [];

while ($row = mysqli_fetch_assoc($outgoing_result)) {
    $outgoing_rows[] = $row;
}

$friends_stmt = mysqli_prepare($mysqli, '
SELECT `u`.`userId`, `u`.`username`, `u`.`displayName`, `u`.`hasAvatar`, `u`.`createdAt`
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = IF(`f`.`requesterId` = ?, `f`.`addresseeId`, `f`.`requesterId`)
    WHERE `f`.`status` = ? AND (`f`.`requesterId` = ? OR `f`.`addresseeId` = ?)
');
mysqli_stmt_bind_param($friends_stmt, 'isii', $current_user -> userId, $accepted_status, $current_user -> userId, $current_user -> userId);
mysqli_stmt_execute($friends_stmt);
$friends_result = mysqli_stmt_get_result($friends_stmt);

$friends_rows = [];

while ($row = mysqli_fetch_assoc($friends_result)) {
    $friends_rows[] = $row;
}

$page = Page::create('Friends');

if ($incoming_rows !== []) {
    $page -> addContents(PendingFriendRequestList::fromRows($incoming_rows));
}

$page -> addContents(FriendList::fromRows($friends_rows));

if ($outgoing_rows !== []) {
    $page -> addContents(OutgoingFriendRequestList::fromRows($outgoing_rows));
}

$page -> send();
