<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$current_user = Auth::user();

// Everything on Glommer is public - the feed is global, not gated by friendship.
$limit = 20;

['rows' => $feed_rows, 'hasMore' => $has_more] = Post::globalFeedRows($limit);

$page = Page::create('Home', needsEditor: $current_user !== null, needsMath: true, needsEmoji: $current_user !== null);

$page -> addMetaContent(new RSSLink(ServerURL::absolute('/feed.xml'), 'RSS Feed'));

if ($current_user !== null) {
    $page -> addContent(new PostComposer());
} else {
    $page -> addContent(new LoginPrompt('post'));
}

$page -> addContent(FeedList::fromRows('global', $feed_rows, $has_more));

$page -> send();
