<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$limit = 50;

['rows' => $feed_rows] = Post::globalFeedRows($limit);

$site_title = Config::get('siteTitle');

$feed = new RSSFeed($site_title, ServerURL::absolute('/'), $site_title . ' - a place to publish.');

foreach (Post::fromRowsWithItems($feed_rows) as $post) {
    $feed -> addItem(RSSItem::fromPost($post));
}

$feed -> send();
