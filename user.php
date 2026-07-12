<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$username = (string) ($_GET['username'] ?? '');
$mysqli = Database::connection();

$stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE `username` = ?
');
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row === null || (int) $row['banned'] === 1) {
    require __DIR__ . '/404.php';
    exit;
}

$user_id = (int) $row['userId'];

if ($user_id === Auth::id()) {
    $profile_user = new CurrentUser();
} else {
    $profile_user = OtherUser::fromRow($row);
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

$page -> addContents($profile_user);

$limit = 20;
$fetch_limit = $limit + 1;

$feed_stmt = mysqli_prepare($mysqli, '
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
    $page -> addContents(new Heading2('Posts'));
    $page -> addContents(FeedList::fromRows('user', $feed_rows, $has_more, $user_id));
}

$page -> send();
