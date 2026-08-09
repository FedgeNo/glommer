<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$slug = mb_strtolower(trim((string) ($_GET['slug'] ?? '')));

// /topics/ - everything that is trending, of every kind.
if ($type === '') {
    $page = new Page([
        'title' => 'Topics',
        'description' => 'What people are talking about on ' . Config::get('siteTitle') . ' right now.',
    ]);

    $page -> addContent(new TrendingEntitySection());
    $page -> send();
    exit;
}

// A kind the extractor cannot produce is not a page, however well-formed the
// address looks.
if (!EntityType::isKnown($type)) {
    require __DIR__ . '/404.php';
    exit;
}

// /topics/{type}/ - the trending topics of one kind.
if ($slug === '') {
    $section = new TypedTrendingEntitySection(['type' => $type]);

    $page = new Page([
        'title' => EntityType::plural($type),
        'description' => EntityType::plural($type) . ' people are talking about on ' . Config::get('siteTitle') . ' right now.',
    ]);

    $page -> addContent($section -> hasItems() ? $section : new Notice('Nothing of this kind is trending.'));
    $page -> send();
    exit;
}

// /topics/{type}/{slug} - one topic. Nothing by that name having trended is a
// 404: there is nothing to show, and it keeps empty pages out of search.
$entity = Trending::entity($type, $slug);

if ($entity === null) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page([
    'title' => (string) $entity -> title,
    'description' => EntityType::label($type) . ' - posts mentioning ' . $entity -> title . ' on ' . Config::get('siteTitle') . '.',
    'needsMath' => true,
    'needsEditor' => Auth::check(),
]);

// The heading carries the kind and the way to search for this - both above
// the paragraph, because the paragraph is the one thing here that can arrive
// after the page has: written to order for a topic the timer has not reached
// yet, and anything below it would jump when it lands.
$page -> addContent(new TopicHeading($entity));

$summary = TopicSummary::for($type, $slug);

if ($summary !== null) {
    // Handed over whole: the page cuts it once for the search snippet and
    // again, longer, for a shared card.
    $page -> description = $summary;
}

// Rendered either way: with words when there are some, and as an empty slot
// the client fills when there are not.
$page -> addContent(new TopicSummaryCard($summary, $type, $slug));

$feed = new SearchFeedList(['query' => (string) $entity -> title]);

$page -> addContent($feed -> hasItems() ? $feed : new Notice('No posts mention this right now.'));

$page -> send();
