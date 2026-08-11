<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// The type in the address is its plural, which is not the label the extractor
// and the table use.
$type_slug = strtolower(trim((string) ($_GET['type'] ?? '')));
$type = $type_slug === '' ? '' : EntityType::fromSlug($type_slug);
$slug = mb_strtolower(trim((string) ($_GET['slug'] ?? '')));

// /topics/ - everything that is trending, of every kind.
if ($type === '') {
    $page = new Page([
        'title' => (string) (Strings::for('PageTitle')['topics'] ?? ''),
        'description' => str_replace('{siteTitle}', (string) Config::get('siteTitle'), (string) (Strings::for('PageTitle')['topicsDescription'] ?? '')),
    ]);

    $page -> addContent(new TrendingEntitySection());

    // Trending is a short top, so most of what this server knows about is not
    // on it at any moment. These lead to the standing list of each kind.
    $page -> addContent(new EntityTypeLinks());

    $page -> send();
    exit;
}

// A kind the extractor cannot produce is not a page, however well-formed the
// address looks.
if ($type === null) {
    require __DIR__ . '/404.php';
    exit;
}

// /topics/{type}/ - every topic of one kind, most talked about first.
if ($slug === '') {
    $section = new PopularEntitySection(['type' => $type]);

    $page = new Page([
        'title' => EntityType::plural($type),
        'description' => strtr((string) (Strings::for('PageTitle')['topicsTypeDescription'] ?? ''), [
            '{typePlural}' => EntityType::plural($type),
            '{siteTitle}' => (string) Config::get('siteTitle'),
        ]),
    ]);

    $page -> addContent($section -> hasItems()
        ? $section
        : new Notice((string) (Strings::for('PopularEntityList')['emptyNotice'] ?? '')));

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
    'description' => strtr((string) (Strings::for('PageTitle')['topicsEntityDescription'] ?? ''), [
        '{typeLabel}' => EntityType::label($type),
        '{entityTitle}' => (string) $entity -> title,
        '{siteTitle}' => (string) Config::get('siteTitle'),
    ]),
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

$page -> addContent($feed -> hasItems()
    ? $feed
    : new Notice((string) (Strings::for('TopicHeading')['noPosts'] ?? '')));

$page -> send();
