<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$limit = 50;

['rows' => $feed_rows] = Post::globalFeedRows($limit);

$config = require __DIR__ . '/src/config.php';

$feed = new RSSFeed($config['siteTitle'], ServerURL::absolute('/'), $config['siteTitle'] . ' - a place to publish.');

foreach (Thread::fromRows($feed_rows) as $thread) {
    $feed -> addItem(RSSItem::fromPost($thread -> post));
}

$feed -> send();
