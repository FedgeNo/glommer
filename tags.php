<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$tag = strtolower(trim((string) ($_GET['tag'] ?? '')));

// /tags/ - the public hashtag directory: the Popular graph and the Trending
// cloud, each a self-fetching list reading its own materialized table.
if ($tag === '') {
    $popular = new HashtagGraph(40);
    $trending = new TrendingHashtagList(20);

    $page = new Page(['title' => 'Tags', 'description' => 'Browse trending and popular hashtags on Glommer.', 'needsTagGraph' => true]);

    if (!$popular -> hasItems() && !$trending -> hasItems()) {
        $page -> addContent(new Notice('No hashtags yet.'));
    } else {
        if ($popular -> hasItems()) {
            $page -> addContent($popular);
        }

        if ($trending -> hasItems()) {
            $page -> addContent($trending);
        }
    }

    $page -> send();
    exit;
}

// /tags/{tag} - the posts carrying one tag. A tag with no posts is a 404
// (nothing to show, and it keeps empty/thin pages out of search).
if (!preg_match('/^[a-z0-9_]{1,50}$/', $tag)) {
    require __DIR__ . '/404.php';
    exit;
}

$feed = new FeedList(['feedType' => 'tag', 'tag' => $tag]);

// A tag with no posts is a 404 (nothing to show, and it keeps empty/thin
// pages out of search).
if (!$feed -> hasItems()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => '#' . $tag, 'description' => 'Posts tagged #' . $tag . ' on Glommer.', 'needsMath' => true]);

$page -> addContent($feed);

$page -> send();
