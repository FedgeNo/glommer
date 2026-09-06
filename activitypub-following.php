<?php

declare(strict_types=1);

define('IS_STATELESS_REQUEST', true);

require __DIR__ . '/src/init.php';

ActivityPubResponse::requireMethod(['GET']);

// Public - the remote accounts this member follows. Only accepted follows
// appear: one still pending is a request the far side has not answered, and
// publishing it would state a relationship that does not exist yet.
$user = ActivityPubResponse::localUser((string) ($_GET['username'] ?? ''));

if ($user === null) {
    ActivityPubResponse::notFound();
}

$id = ActivityPubActor::followingFor($user);
$total = RemoteFollow::acceptedCountFor((int) $user -> userId);
$page = ActivityPubCollection::requestedPage();

if ($page === null) {
    ActivityPubResponse::send(ActivityPubCollection::describe($id, $total));
}

ActivityPubResponse::send(ActivityPubCollection::page(
    $id,
    $total,
    $page,
    RemoteFollow::acceptedActorURIsFor((int) $user -> userId, $page)
));
