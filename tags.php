<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$tag = strtolower(trim((string) ($_GET['tag'] ?? '')));

// /tags/ - the public hashtag directory: trending and most-popular tags.
if ($tag === '') {
    $graph = Hashtag::graphData(40);
    $trending = Hashtag::trending(20);

    $page = Page::create('Tags', 'Browse trending and popular hashtags on Glommer.', needsTagGraph: true);

    if ($graph['nodes'] === [] && $trending === []) {
        $page -> addContent(new Notice('No hashtags yet.'));
    } else {
        if ($graph['nodes'] !== []) {
            $page -> addContent(new Heading2('Popular'));
            $page -> addContent(new HashtagGraph($graph));
        }

        if ($trending !== []) {
            $page -> addContent(new HashtagCloud('Trending', $trending));
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

['rows' => $rows, 'hasMore' => $has_more] = Hashtag::postRows($tag, 20);

if ($rows === []) {
    require __DIR__ . '/404.php';
    exit;
}

$page = Page::create('#' . $tag, 'Posts tagged #' . $tag . ' on Glommer.', needsMath: true);

$list = FeedList::fromRows('tag', $rows, $has_more);
$list -> tag = $tag;
$page -> addContent($list);

$page -> send();
