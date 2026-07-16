<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();
$username = (string) ($_GET['username'] ?? '');

if ($username === '') {
    $page = Page::create('Messages');

    $not_banned = 0;

    $conversations = DB::rows('
SELECT `u`.`userId`, `u`.`username`, `u`.`displayName`, `u`.`hasAvatar`, MAX(`m`.`createdAt`) AS `lastMessageAt`
    FROM `Messages` `m`
    JOIN `Users` `u` ON `u`.`userId` = IF(`m`.`senderId` = ?, `m`.`recipientId`, `m`.`senderId`)
    WHERE (`m`.`senderId` = ? OR `m`.`recipientId` = ?) AND `u`.`banned` = ?
    GROUP BY `u`.`userId`, `u`.`username`, `u`.`displayName`, `u`.`hasAvatar`
    ORDER BY `lastMessageAt` DESC
', 'Conversation', 'iiii', $current_user -> userId, $current_user -> userId, $current_user -> userId, $not_banned);

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
$name = $other_user -> displayName ?? $other_user -> username;

$page = Page::create('Messages with ' . $name, needsMath: true, needsEmoji: true, body_class: 'MessagesPage');

if (Block::exists($current_user -> userId, $other_user_id)) {
    $page -> addContent(new Notice('You can\'t message this user.'));
    $page -> send();
    exit;
}

$conversation_users = [
    $current_user -> userId => [
        'username' => $current_user -> username,
        'displayName' => $current_user -> displayName,
        'image' => $current_user -> avatarURL(),
    ],
    $other_user -> userId => [
        'username' => $other_user -> username,
        'displayName' => $other_user -> displayName,
        'image' => $other_user -> avatarURL(),
    ],
];

$page -> addContent(new JSGlobals(['conversationUsers' => $conversation_users]));

['rows' => $history_rows, 'hasMore' => $has_more] = Message::rowsBetween($current_user -> userId, $other_user_id, 20);

$page -> addContent(MessageList::fromRows($other_user_id, $history_rows, $has_more));

$page -> addContent(new MessageComposer($other_user_id));

$page -> send();
