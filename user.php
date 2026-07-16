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
    $profile_user = new CurrentUser();
}

$name = $profile_user -> displayName ?? $profile_user -> username;

$json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => $name,
    'url' => Page::currentURL(),
];

if ($profile_user -> avatarURL() !== null) {
    $json_ld['image'] = $profile_user -> avatarURL();
}

$page = Page::create($name, 'Posts by ' . $name . ' on Glommer', $profile_user -> avatarURL(), $json_ld, needsMath: true);

$page -> addMetaContent(new RSSLink(ServerURL::absolute('/users/' . $profile_user -> username . '/feed.xml'), $name . ' - RSS Feed'));

$page -> addContent($profile_user);

$limit = 20;
$fetch_limit = $limit + 1;

$feed_stmt = mysqli_prepare(Database::connection(), '
SELECT *
    FROM `Posts`
    WHERE `parentId` IS NULL AND `userId` = ?
    ORDER BY `postId` DESC
    LIMIT ?
');
mysqli_stmt_bind_param($feed_stmt, 'ii', $user_id, $fetch_limit);
mysqli_stmt_execute($feed_stmt);
$feed_result = mysqli_stmt_get_result($feed_stmt);

$feed_rows = [];

while ($row = mysqli_fetch_assoc($feed_result)) {
    $feed_rows[] = $row;
}

$has_more = count($feed_rows) > $limit;

if ($has_more) {
    array_pop($feed_rows);
}

if ($feed_rows !== []) {
    if (Auth::check()) {
        // Search this user's own posts (scoped to their userId). While a query is
        // active the default feed below is hidden and the results take its place
        // (see main.js); clearing the box brings the feed back.
        $page -> addContent(new PostSearch($user_id, 'Search ' . $name . '\'s posts...'));
    }

    $feed_section = new Div();
    $feed_section -> class = 'ProfileFeed';
    $feed_section -> addContent(new Heading2('Posts'));
    $feed_section -> addContent(FeedList::fromRows('user', $feed_rows, $has_more, $user_id));

    $page -> addContent($feed_section);
}

$page -> send();
