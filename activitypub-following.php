<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the remote accounts this member follows.
$user = ActivityPubResponse::localUser((string) ($_GET['username'] ?? ''));

if ($user === null) {
    ActivityPubResponse::notFound();
}

ActivityPubResponse::send(ActivityPubResponse::orderedCollection(
    ActivityPubActor::followingFor($user),
    RemoteFollow::acceptedActorURIsFor((int) $user -> userId)
));
