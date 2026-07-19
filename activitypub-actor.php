<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Remote servers dereference this on every signature check and never carry a
// cookie, so the session init.php opened is discarded rather than left as an
// orphaned file. Only when no cookie was presented, so a signed-in person
// opening this URL in a browser keeps their session.
if (!isset($_COOKIE[session_name()])) {
    session_destroy();
}

// Public - the one site-wide ActivityPub actor identity (see ActivityPubKeys):
// what a remote server dereferences to get this instance's inbox URL and
// public key when verifying an outbound Follow, or when a local user's
// follow request needs somewhere for the remote side to send an Accept.
if (!ActivityPubKeys::isConfigured()) {
    http_response_code(404);
    exit;
}

$actor_url = ServerURL::absolute('/activitypub/actor');

header('Content-Type: application/activity+json');
echo json_encode([
    '@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1'],
    'id' => $actor_url,
    'type' => 'Application',
    'preferredUsername' => 'glommer',
    'name' => Config::get('siteTitle'),
    'inbox' => ServerURL::absolute('/activitypub/inbox'),
    'publicKey' => [
        'id' => $actor_url . '#main-key',
        'owner' => $actor_url,
        'publicKeyPem' => ActivityPubKeys::publicKeyPem(),
    ],
], JSON_UNESCAPED_SLASHES);
