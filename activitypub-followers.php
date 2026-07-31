<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - who out on the Fediverse follows this member. Remote servers read it
// to reconcile their own state, and people read it to see who is listening
// before deciding whether to follow back.
$user = ActivityPubResponse::localUser((string) ($_GET['username'] ?? ''));

if ($user === null) {
    ActivityPubResponse::notFound();
}

ActivityPubResponse::send(ActivityPubResponse::orderedCollection(
    ActivityPubActor::followersFor($user),
    FediverseFollower::actorURIsFor((int) $user -> userId)
));
