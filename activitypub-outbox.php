<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - this member's own posts as activities, which is what a remote server
// reads to backfill someone it has just started following.
//
// Paged the way ActivityPub expects: the collection itself carries the total
// and points at its first page, and each page carries the items and a link
// onward. Without ?page= this is the collection; with it, one page of it.
$user = ActivityPubResponse::localUser((string) ($_GET['username'] ?? ''));

if ($user === null) {
    ActivityPubResponse::notFound();
}

$outbox_uri = ActivityPubActor::outboxFor($user);
$total = Post::publishedCountFor((int) $user -> userId);

if (!isset($_GET['page'])) {
    ActivityPubResponse::send([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $outbox_uri,
        'type' => 'OrderedCollection',
        'totalItems' => $total,
        'first' => $outbox_uri . '?page=1',
    ]);
}

$page = max(1, (int) $_GET['page']);
$per_page = ActivityPubOutbox::PAGE_SIZE;
$activities = ActivityPubOutbox::activitiesFor($user, $page);

$document = [
    '@context' => 'https://www.w3.org/ns/activitystreams',
    'id' => $outbox_uri . '?page=' . $page,
    'type' => 'OrderedCollectionPage',
    'partOf' => $outbox_uri,
    'totalItems' => $total,
    'orderedItems' => $activities,
];

// Only when there is actually another page: a next link that leads to an empty
// one makes a crawler walk forever.
if ($page * $per_page < $total) {
    $document['next'] = $outbox_uri . '?page=' . ($page + 1);
}

if ($page > 1) {
    $document['prev'] = $outbox_uri . '?page=' . ($page - 1);
}

ActivityPubResponse::send($document);
