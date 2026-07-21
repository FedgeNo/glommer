<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$username = (string) ($_GET['username'] ?? '');

$profile_user = User::byUsername($username);

if ($profile_user === null) {
    require __DIR__ . '/404.php';
    exit;
}

if (!$profile_user -> title) {
    $profile_user -> title = $profile_user -> slug;
}

$user_id = (int) $profile_user -> userId;

if ($user_id === Auth::id()) {
    $profile_user = new CurrentUser($profile_user);
}

$page = new Page($profile_user);
$page -> bodyClass = 'ProfilePage';
$page -> image = $profile_user -> avatarURL();
$page -> jsonLD = [
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => $profile_user -> title,
    'url' => Page::currentURL(),
];

if ($profile_user -> avatarURL() !== null) {
    $page -> jsonLD['image'] = $profile_user -> avatarURL();
}

$page -> needsMath = true;
$page -> needsEditor = Auth::check();

$page -> rssLink = new RSSLink(ServerURL::absolute('/users/' . $profile_user -> slug . '/feed.xml'), $profile_user -> title);

$page -> addContent($profile_user);

$profile_feed = new ProfileFeedSection(['userId' => $user_id]);

if ($profile_feed -> hasItems()) {
    if (Auth::check()) {
        // Search this user's own posts (scoped to their userId). While a query is
        // active the default feed below is hidden and the results take its place
        // (see main.js); clearing the box brings the feed back.
        $page -> addContent(new PostSearch(['authorId' => $user_id, 'placeholder' => 'Search ' . $profile_user -> title . '\'s posts...']));
        $page -> addContent(new SearchFeedSection(['authorId' => $user_id]));
    }

    $page -> addContent($profile_feed);
}

$page -> send();
