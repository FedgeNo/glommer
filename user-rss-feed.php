<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$username = (string) ($_GET['username'] ?? '');

$profile_user = User::byUsername($username);

// A followed Fediverse account's profile is browsable on the site, but its
// posts are not ours to re-publish as a feed of our own - syndicating someone
// else's server's content from our domain is a different thing entirely to
// showing it to the person who chose to follow them.
if ($profile_user === null || $profile_user -> remoteActorURI !== null) {
    require __DIR__ . '/404.php';
    exit;
}

new UserRSSFeed(['user' => $profile_user]) -> send();
