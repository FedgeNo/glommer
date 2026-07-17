<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$username = (string) ($_GET['username'] ?? '');

$profile_user = User::byUsername($username);

if ($profile_user === null) {
    require __DIR__ . '/404.php';
    exit;
}

$user_id = (int) $profile_user -> userId;

if ($user_id === Auth::id()) {
    $profile_user = new CurrentUser($profile_user);
}

$name = $profile_user -> title ?? $profile_user -> slug;

$page = new Page($profile_user);
$page -> bodyClass = 'ProfilePage';
$page -> title = $name;
$page -> description = 'Posts by ' . $name . ' on Glommer';
$page -> image = $profile_user -> avatarURL();
$page -> jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => $name,
    'url' => Page::currentURL(),
];

if ($profile_user -> avatarURL() !== null) {
    $page -> jsonLd['image'] = $profile_user -> avatarURL();
}

$page -> needsMath = true;
$page -> rssLink = new RSSLink(ServerURL::absolute('/users/' . $profile_user -> slug . '/feed.xml'), $name . ' - RSS Feed');

$page -> addContent($profile_user);

$feed = new FeedList(['feedType' => 'user', 'userId' => $user_id]);

if ($feed -> hasItems()) {
    if (Auth::check()) {
        // Search this user's own posts (scoped to their userId). While a query is
        // active the default feed below is hidden and the results take its place
        // (see main.js); clearing the box brings the feed back.
        $page -> addContent(new PostSearch($user_id, 'Search ' . $name . '\'s posts...'));
    }

    $feed_section = new Div();
    $feed_section -> class = 'ProfileFeed';
    $feed_section -> addContent(new Heading2('Posts'));
    $feed_section -> addContent($feed);

    $page -> addContent($feed_section);
}

$page -> send();
