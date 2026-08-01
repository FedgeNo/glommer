<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the posts this member has pinned to their profile, which is where
// the rest of the network looks for exactly that.
//
// Object URIs rather than inlined objects: every one of them is separately
// dereferenceable, and a server that wants the content can ask for it.
$user = ActivityPubResponse::localUser((string) ($_GET['username'] ?? ''));

if ($user === null) {
    ActivityPubResponse::notFound();
}

ActivityPubResponse::send(ActivityPubResponse::orderedCollection(
    ActivityPubActor::featuredFor($user),
    PinnedPost::objectURIsFor($user)
));
