<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$current_user = Auth::user();

$page = new Page(['needsEditor' => $current_user !== null, 'needsMath' => true, 'needsEmoji' => $current_user !== null]);

$page -> rssLink = new RSSLink(ServerURL::absolute('/feed.xml'), 'RSS Feed');

$page -> addContent(new PostComposer());

// Everything on Glommer is public - the feed is global, not gated by friendship.
$page -> addContent(new GlobalFeedList());

$page -> send();
