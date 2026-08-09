<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$tag = mb_strtolower(trim((string) ($_GET['tag'] ?? '')));

// /tags/ - the public hashtag directory: the Popular graph and the Trending
// cloud, each a self-fetching list reading its own materialized table.
if ($tag === '') {
    $popular = new HashtagGraphSection();
    $trending = new TrendingHashtagSection();

    $page = new Page(['title' => 'Tags', 'description' => 'Browse trending and popular hashtags on ' . Config::get('siteTitle') . '.', 'needsTagGraph' => true]);

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
// The renderer's own rule for what a tag is, so a tag it linked always leads
// somewhere rather than to a 404.
if (!Linkifier::isTagSlug($tag)) {
    require __DIR__ . '/404.php';
    exit;
}

$feed = new TagFeedList(['tag' => $tag]);

// A tag with no posts is a 404 (nothing to show, and it keeps empty/thin
// pages out of search).
if (!$feed -> hasItems()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => '#' . $tag, 'description' => 'Posts tagged #' . $tag . ' on ' . Config::get('siteTitle') . '.', 'needsMath' => true, 'needsEditor' => Auth::check()]);

// What people are posting about here, when the trending timer has had a
// chance to write one - absent otherwise, and the page reads as it always
// did. Its text also makes a better meta description than the boilerplate.
$summary = TopicSummary::for('hashtag', $tag);

if ($summary !== null) {
    // Handed over whole: the page cuts it once for the search snippet and
    // again, longer, for a shared card.
    $page -> description = $summary;
}

// Written to order for a reader who got here before the timer did - but only
// for a tag that is actually trending, since that is all the summariser will
// write about and an empty slot would otherwise ask for one on every tag page
// on the site.
if ($summary !== null || Trending::entity('hashtag', $tag) !== null) {
    $page -> addContent(new TopicSummaryCard($summary, 'hashtag', $tag));
}

$page -> addContent($feed);

$page -> send();
