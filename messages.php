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

// Only a thread wants these, so they ride on this page's config rather than
// being restated in every response's site-wide block.
$page -> clientConfig['conversationUsers'] = $conversation_users;
$page -> clientConfig['iceServers'] = VideoCall::iceServers();

$page -> addContent(new MessageList([
    'userId' => (int) $current_user -> userId,
    'otherUserId' => $other_user -> userId,
    'otherUserIsLocal' => $other_user -> remoteActorURI === null,
]));

// A conversation with someone on another server is stored on that server too,
// in the clear, and its administrator can read it. The thread looks identical
// otherwise, so it says so. Between two members here, the honest note runs the
// other way: encrypted when both have keys, and if not, whose move it is.
//
// Below the list, not above it: the thread opens scrolled to the bottom, so
// this is where the reader actually is - and above the list it can't be read
// at all, because scrolling up to it triggers the infinite scroll and loads
// more history underneath it.
if ($other_user -> remoteActorURI !== null) {
    $page -> addContent(new FederatedThreadNotice('@' . $other_user -> slug));
} elseif ($current_user -> messagePublicKey !== null && $other_user -> messagePublicKey !== null) {
    // What the browser needs to take part: the other side's public key to
    // derive the conversation key against, and the viewer's own wrapped
    // private key for the unlock form to open. Ciphertext both - the server
    // is handing over exactly what it stores.
    $page -> clientConfig['messageEncryption'] = [
        'otherPublicKey' => json_decode((string) $other_user -> messagePublicKey, true),
        'wrappedPrivateKey' => json_decode((string) $current_user -> messageWrappedPrivateKey, true),
    ];

    $page -> addContent(new EncryptedThreadNotice());
    $page -> addContent(new MessageUnlockForm());
} else {
    $page -> addContent(new MessageEncryptionNudge($current_user -> messagePublicKey !== null, '@' . $other_user -> slug));
}

$page -> addContent(new MessageComposer($other_user -> userId));

$page -> send();
