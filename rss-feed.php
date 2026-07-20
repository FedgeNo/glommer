<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// The same rows the on-site global feed shows, rendered as RSS - one query,
// so the two can't drift into disagreeing about what's public.
$feed_rows = FeedList::globalRows(50);

$site_title = Config::get('siteTitle');

$feed = new RSSFeed($site_title, ServerURL::absolute('/'), $site_title . ' - a place to publish.');

foreach (Post::fromRowsWithItems($feed_rows) as $post) {
    $feed -> addItem(RSSItem::fromPost($post));
}

$feed -> send();
