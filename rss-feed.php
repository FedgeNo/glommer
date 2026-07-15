<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$limit = 50;

['rows' => $feed_rows] = Post::globalFeedRows($limit);

$site_title = Config::get('siteTitle');

$feed = new RSSFeed($site_title, ServerURL::absolute('/'), $site_title . ' - a place to publish.');

foreach (Thread::fromRows($feed_rows) as $thread) {
    $feed -> addItem(RSSItem::fromPost($thread -> post));
}

$feed -> send();
