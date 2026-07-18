<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$limit = 50;
$not_banned = 0;

// The newest top-level posts by non-banned authors - the global feed, as RSS.
// STRAIGHT_JOIN for the same reason as FeedList's global query: driving from
// Posts walks parentId_postId backward and stops at the limit, instead of
// collecting and filesorting every author's top-level posts.
$feed_rows = DB::rows('
SELECT STRAIGHT_JOIN `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'Post', 'ii', $not_banned, $limit);

$site_title = Config::get('siteTitle');

$feed = new RSSFeed($site_title, ServerURL::absolute('/'), $site_title . ' - a place to publish.');

foreach (Post::fromRowsWithItems($feed_rows) as $post) {
    $feed -> addItem(RSSItem::fromPost($post));
}

$feed -> send();
