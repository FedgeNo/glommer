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

// What this conversation is - end-to-end encrypted, plaintext until someone
// (named) sets up keys, or federated and stored on a second server - rides on
// the composer as a small chip with the full explanation a click away. On the
// composer because that is the one fixed place in a thread: the page opens
// scrolled to the bottom, and anything in the scrolling flow is fair game for
// the infinite scroll to bury.
if ($other_user -> remoteActorURI !== null) {
    $privacy_state = 'federated';
} elseif ($current_user -> messagePublicKey !== null && $other_user -> messagePublicKey !== null) {
    $privacy_state = 'encrypted';

    // What the browser needs to take part: the other side's public key to
    // derive the conversation key against, and the viewer's own wrapped
    // private key for the unlock form to open. Ciphertext both - the server
    // is handing over exactly what it stores.
    $page -> clientConfig['messageEncryption'] = [
        'otherPublicKey' => json_decode((string) $other_user -> messagePublicKey, true),
        'wrappedPrivateKey' => json_decode((string) $current_user -> messageWrappedPrivateKey, true),
    ];

    $page -> addContent(new MessageUnlockForm());
} elseif ($current_user -> messagePublicKey !== null) {
    $privacy_state = 'awaiting-theirs';
} else {
    $privacy_state = 'awaiting-yours';
}

$page -> addContent(new MessageComposer($other_user -> userId, new MessagePrivacyButton($privacy_state, '@' . $other_user -> slug)));

$page -> send();
