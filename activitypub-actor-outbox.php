<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the instance actor's outbox. The site itself never posts, so this
// is permanently empty; it exists because the actor document names it, and a
// named collection that 404s reads as a broken server to anything strict.
if (!ActivityPubKeys::isConfigured()) {
    http_response_code(404);
    exit;
}

ActivityPubResponse::send(ActivityPubResponse::orderedCollection(
    ServerURL::absolute('/activitypub/actor/outbox'),
    []
));
