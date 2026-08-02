<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$username = (string) ($_GET['username'] ?? '');

$profile_user = User::byUsername($username);

if ($profile_user === null) {
    require __DIR__ . '/404.php';
    exit;
}

// This URL is also the member's ActivityPub actor. One canonical address per
// person means a pasted profile link resolves both in a browser and in a
// Fediverse search, and nothing has to keep two URLs agreeing about who
// someone is - so which document comes back is decided by what the caller
// asked for, before any of the page is built.
//
// Only for local members: a shadow row for a remote account is somebody else's
// actor, and answering for it would be claiming to speak for them.
if (ActivityPubActor::wantsActivityJSON((string) ($_SERVER['HTTP_ACCEPT'] ?? ''))
    && $profile_user -> remoteActorURI === null
    && (int) $profile_user -> banned !== 1) {
    $actor = ActivityPubActor::document($profile_user);

    if ($actor !== null) {
        ActivityPubResponse::send($actor);
    }
}

// A Fediverse account's profile exists here only so a member can decide
// whether to follow it - we're not the ones representing that person to the
// public, so an anonymous visitor doesn't get to browse it.
if ($profile_user -> remoteActorURI !== null) {
    Auth::requireLogin();
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

// Above the ordinary posts, which is the whole point of pinning one.
$page -> addContent(new PinnedPostSection(['userId' => $user_id]));

$profile_feed = new ProfileFeedSection(['userId' => $user_id]);

if ($profile_feed -> hasItems()) {
    if (Auth::check()) {
        // Search this user's own posts (scoped to their userId). While a query is
        // active the default feed below is hidden and the results take its place
        // (see main.js); clearing the box brings the feed back.
        $page -> addContent(new PostSearch(['userId' => $user_id, 'placeholder' => 'Search ' . $profile_user -> title . '\'s posts...']));
        $page -> addContent(new SearchFeedSection(['userId' => $user_id]));
    }

    $page -> addContent($profile_feed);
}

$page -> send();
