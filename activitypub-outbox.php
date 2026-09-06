<?php

declare(strict_types=1);

define('IS_STATELESS_REQUEST', true);

require __DIR__ . '/src/init.php';

ActivityPubResponse::requireMethod(['GET']);

// Public - this member's own posts as activities, which is what a remote server
// reads to backfill someone it has just started following.
$user = ActivityPubResponse::localUser((string) ($_GET['username'] ?? ''));

if ($user === null) {
    ActivityPubResponse::notFound();
}

$id = ActivityPubActor::outboxFor($user);
$total = Post::publishedCountFor((int) $user -> userId);
$page = ActivityPubCollection::requestedPage();

if ($page === null) {
    ActivityPubResponse::send(ActivityPubCollection::describe($id, $total));
}

ActivityPubResponse::send(ActivityPubCollection::page(
    $id,
    $total,
    $page,
    ActivityPubOutbox::activitiesFor($user, $page)
));
