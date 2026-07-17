<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();
$username = (string) ($_GET['username'] ?? '');

if ($username === '') {
    $page = new Page(['title' => 'Messages']);

    $not_banned = 0;

    // Each direction is its own indexed half (senderId=me walks
    // senderId_recipientId_messageId, recipientId=me walks
    // recipientId_senderId_messageId), so the scan is bounded to this user's
    // own messages rather than the whole table - a single OR across the two
    // columns can't use either index and degrades to a full scan for a
    // heavy-sending account. The halves collapse to one row per partner
    // (their latest message) before the join to Users for display.
    $conversations = DB::rows('
SELECT `u`.`userId`, `u`.`slug`, `u`.`title`, `u`.`hasAvatar`, `partners`.`lastMessageAt`
    FROM (
        SELECT `partnerId`, MAX(`createdAt`) AS `lastMessageAt`
            FROM (
                SELECT `recipientId` AS `partnerId`, `createdAt`
                    FROM `Messages`
                    WHERE `senderId` = ?
                UNION ALL
                SELECT `senderId` AS `partnerId`, `createdAt`
                    FROM `Messages`
                    WHERE `recipientId` = ?
            ) AS `mine`
            GROUP BY `partnerId`
    ) AS `partners`
    JOIN `Users` `u` ON `u`.`userId` = `partners`.`partnerId`
    WHERE `u`.`banned` = ?
    ORDER BY `partners`.`lastMessageAt` DESC
', 'Conversation', 'iii', $current_user -> userId, $current_user -> userId, $not_banned);

    $has_conversations = $conversations !== [];

    foreach ($conversations as $conversation) {
        $page -> addContent($conversation);
    }

    if (!$has_conversations) {
        $page -> addContent(new Notice('You don\'t have any conversations yet.'));
    }

    $page -> send();
    exit;
}

$other_user = User::loadByUsername($username);

// A banned user is treated as nonexistent here (same as a bad username) - no
// thread view, no way to message them.
if ($other_user === null || $other_user -> banned !== 0) {
    require __DIR__ . '/404.php';
    exit;
}

$other_user_id = $other_user -> userId;
$name = $other_user -> title ?: $other_user -> slug;

$page = new Page(['title' => 'Messages with ' . $name, 'needsMath' => true, 'needsEmoji' => true, 'bodyClass' => 'MessagesPage']);

if (Block::exists($current_user -> userId, $other_user_id)) {
    $page -> addContent(new Notice('You can\'t message this user.'));
    $page -> send();
    exit;
}

$conversation_users = [
    $current_user -> userId => [
        'slug' => $current_user -> slug,
        'title' => $current_user -> title,
        'image' => $current_user -> avatarURL(),
    ],
    $other_user -> userId => [
        'slug' => $other_user -> slug,
        'title' => $other_user -> title,
        'image' => $other_user -> avatarURL(),
    ],
];

$page -> addContent(new JSGlobals(['conversationUsers' => $conversation_users]));

['rows' => $history_rows, 'hasMore' => $has_more] = Message::rowsBetween($current_user -> userId, $other_user_id, 20);

$page -> addContent(MessageList::fromRows($other_user_id, $history_rows, $has_more));

$page -> addContent(new MessageComposer($other_user_id));

$page -> send();
