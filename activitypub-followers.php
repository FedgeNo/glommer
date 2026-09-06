<?php

declare(strict_types=1);

define('IS_STATELESS_REQUEST', true);

require __DIR__ . '/src/init.php';

ActivityPubResponse::requireMethod(['GET']);

// Public - who out on the Fediverse follows this member. Remote servers read it
// to reconcile their own state, and people read it to see who is listening
// before deciding whether to follow back.
$user = ActivityPubResponse::localUser((string) ($_GET['username'] ?? ''));

if ($user === null) {
    ActivityPubResponse::notFound();
}

$id = ActivityPubActor::followersFor($user);
$total = FediverseFollower::countFor((int) $user -> userId);
$page = ActivityPubCollection::requestedPage();

if ($page === null) {
    ActivityPubResponse::send(ActivityPubCollection::describe($id, $total));
}

ActivityPubResponse::send(ActivityPubCollection::page(
    $id,
    $total,
    $page,
    FediverseFollower::actorURIsFor((int) $user -> userId, $page)
));
