<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - this member's own activities, which is what a remote server reads to
// backfill someone it has just started following.
//
// The count is real; the items are not served yet. Serialising a post as a Note
// means rendering its stored Delta to HTML, which is the same machinery
// publishing needs, so both arrive together in the step that adds delivery
// rather than being written twice. An OrderedCollection is allowed to state its
// size without inlining its members, so this is an honest partial rather than a
// broken endpoint - and the actor document can point at something real in the
// meantime.
$user = ActivityPubResponse::localUser((string) ($_GET['username'] ?? ''));

if ($user === null) {
    ActivityPubResponse::notFound();
}

ActivityPubResponse::send([
    '@context' => 'https://www.w3.org/ns/activitystreams',
    'id' => ActivityPubActor::outboxFor($user),
    'type' => 'OrderedCollection',
    'totalItems' => Post::publishedCountFor((int) $user -> userId),
]);
