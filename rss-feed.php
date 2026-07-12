<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$mysqli = Database::connection();
$limit = 50;
$not_banned = 0;

$feed_stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
mysqli_stmt_bind_param($feed_stmt, 'ii', $not_banned, $limit);
mysqli_stmt_execute($feed_stmt);
$feed_result = mysqli_stmt_get_result($feed_stmt);

$feed_rows = [];

while ($row = mysqli_fetch_assoc($feed_result)) {
    $feed_rows[] = $row;
}

$config = require __DIR__ . '/src/config.php';

$feed = new RSSFeed($config['siteTitle'], ServerURL::absolute('/'), $config['siteTitle'] . ' - a place to publish.');

foreach (Thread::fromRows($feed_rows) as $thread) {
    $feed -> addItem(RSSItem::fromPost($thread -> post));
}

$feed -> send();
