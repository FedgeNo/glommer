<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Fully public - anyone can browse a user's friends. The pending/sent request
// sections only appear when you're looking at your own page.
$username = (string) ($_GET['username'] ?? '');
$mysqli = Database::connection();

$stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE `username` = ?
');
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($row === null || (int) $row['banned'] === 1) {
    require __DIR__ . '/404.php';
    exit;
}

$is_own = Auth::id() === (int) $row['userId'];

// Same profile card user.php renders (CurrentUser for your own page, OtherUser
// otherwise) - shown at the top so there's a way back to the profile.
$profile_user = $is_own ? new CurrentUser() : OtherUser::fromRow($row);
$name = $profile_user -> displayName ?? $profile_user -> username;

$page = Page::create($name . '\'s Friends', 'Friends of ' . $name . ' on Glommer', $profile_user -> avatarURL());

$page -> addContents($profile_user);

if ($is_own) {
    $incoming = PendingFriendRequestList::forUser($profile_user);

    if ($incoming -> items !== []) {
        $page -> addContents($incoming);
    }
}

$page -> addContents(FriendList::forUser($profile_user));

if ($is_own) {
    $outgoing = OutgoingFriendRequestList::forUser($profile_user);

    if ($outgoing -> items !== []) {
        $page -> addContents($outgoing);
    }
}

$page -> send();
