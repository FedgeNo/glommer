<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Fully public - anyone can browse a user's friends. The pending/sent request
// sections only appear when you're looking at your own page.
$username = (string) ($_GET['username'] ?? '');

$profile_user = User::byUsername($username);

if ($profile_user === null) {
    require __DIR__ . '/404.php';
    exit;
}

$is_own = Auth::id() === (int) $profile_user -> userId;

// Same profile card user.php renders (CurrentUser for your own page, OtherUser
// otherwise) - shown at the top so there's a way back to the profile.
if ($is_own) {
    $profile_user = new CurrentUser();
}
$name = $profile_user -> displayName ?? $profile_user -> username;

$page = Page::create($name . '\'s Friends', 'Friends of ' . $name . ' on Glommer', $profile_user -> avatarURL());

$page -> addContent($profile_user);

if ($is_own) {
    $incoming = PendingFriendRequestList::forUser($profile_user);

    if ($incoming -> items !== []) {
        $page -> addContent($incoming);
    }
}

$page -> addContent(FriendList::forUser($profile_user));

if ($is_own) {
    $outgoing = OutgoingFriendRequestList::forUser($profile_user);

    if ($outgoing -> items !== []) {
        $page -> addContent($outgoing);
    }
}

$page -> send();
