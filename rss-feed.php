<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$feed_list = new RSSFeedList();

$site_title = Config::get('siteTitle');

$feed = new RSSFeed($site_title, ServerURL::absolute('/'), $site_title . ' - a place to publish.');

foreach ($feed_list -> items as $post) {
    $feed -> addItem(RSSItem::fromPost($post));
}

$feed -> send();
