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
$profile_user = OtherUser::fromRow($row);
$name = $profile_user -> displayName ?? $profile_user -> username;

$limit = 50;

$feed_stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Posts`
    WHERE `parentId` IS NULL AND `userId` = ?
    ORDER BY `postId` DESC
    LIMIT ?
');
mysqli_stmt_bind_param($feed_stmt, 'ii', $user_id, $limit);
mysqli_stmt_execute($feed_stmt);
$feed_result = mysqli_stmt_get_result($feed_stmt);

$feed_rows = [];

while ($row = mysqli_fetch_assoc($feed_result)) {
    $feed_rows[] = $row;
}

$config = require __DIR__ . '/src/config.php';

$feed = new RSSFeed('Posts by ' . $name . ' on ' . $config['siteTitle'], ServerURL::absolute('/users/' . $profile_user -> username . '/'), 'Posts by ' . $name . ' on ' . $config['siteTitle']);

foreach (Thread::fromRows($feed_rows) as $thread) {
    $feed -> addItem(RSSItem::fromPost($thread -> post));
}

$feed -> send();
