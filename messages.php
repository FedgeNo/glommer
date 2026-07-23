<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$current_user = Auth::user();
$username = (string) ($_GET['username'] ?? '');

if ($username === '') {
    $page = new Page(['title' => 'Messages']);

    $page -> addContent(new ConversationList(['userId' => (int) $current_user -> userId]));

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

$name = $other_user -> title ?: $other_user -> slug;

$page = new Page(['title' => 'Messages with ' . $name, 'needsMath' => true, 'needsEmoji' => true, 'bodyClass' => 'MessagesPage']);

if (Block::exists($current_user -> userId, $other_user -> userId)) {
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

$page -> addContent(new MessageList([
    'userId' => (int) $current_user -> userId,
    'otherUserId' => $other_user -> userId,
]));

$page -> addContent(new MessageComposer($other_user -> userId));

$page -> send();
