<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

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
