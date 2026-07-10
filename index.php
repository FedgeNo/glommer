<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$current_user = Auth::user();
$mysqli = Database::connection();

// Everything on Glommer is public - the feed is global, not gated by friendship.
$limit = 20;
$fetch_limit = $limit + 1;

$not_banned = 0;

$feed_stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
mysqli_stmt_bind_param($feed_stmt, 'ii', $not_banned, $fetch_limit);
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

$page = Page::create('Home', needsEditor: $current_user !== null, needsMath: true, needsEmoji: $current_user !== null);

$page -> addMetaContent(new RSSLink(URL::absolute('/feed.xml'), 'RSS Feed'));

if ($current_user !== null) {
    $page -> addContents(new PostComposer());
} else {
    $page -> addContents(new LoginPrompt('post'));
}

$page -> addContents(FeedList::fromRows('global', $feed_rows, $has_more));

$page -> send();
