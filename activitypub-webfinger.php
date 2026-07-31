<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - WebFinger (RFC 7033). This is how the rest of the network turns a
// handle someone typed into an actor URI it can dereference, so every local
// member resolves here: acct:{username}@{host} points at their profile URL,
// which doubles as their actor document.
//
// The instance's own Application actor (acct:glommer@{host}) still resolves
// too. It is what signs an outbound Follow, and a remote server confirming one
// looks it up the same way.
$host = ActivityPubActor::canonicalHost();
$resource = is_string($_GET['resource'] ?? null) ? $_GET['resource'] : '';

// The host half is compared case-insensitively: it is a hostname, so a caller
// that normalises it differently is asking for the same account, not a missing
// one.
if (!preg_match('/\Aacct:([^@]+)@(.+)\z/i', $resource, $matches) || strcasecmp($matches[2], $host) !== 0) {
    ActivityPubResponse::notFound();
}

$username = $matches[1];

// The one instance-wide identity, which is not a member and has no profile.
if (strcasecmp($username, 'glommer') === 0) {
    if (!ActivityPubKeys::isConfigured()) {
        ActivityPubResponse::notFound();
    }

    header('Content-Type: application/jrd+json');
    ActivityPubResponse::send([
        'subject' => 'acct:glommer@' . $host,
        'aliases' => [ServerURL::absolute('/activitypub/actor')],
        'links' => [
            [
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => ServerURL::absolute('/activitypub/actor'),
            ],
        ],
    ]);
}

$user = ActivityPubResponse::localUser($username);

// Only once the member is known to exist AND to have a usable key: answering
// for someone this server cannot actually sign for would advertise an actor
// that fails every signature check made against it.
if ($user === null || ActivityPubActor::publicKeyPem($user) === null) {
    ActivityPubResponse::notFound();
}

$actor_uri = ActivityPubActor::uriFor($user);

header('Content-Type: application/jrd+json');
ActivityPubResponse::send([
    'subject' => 'acct:' . $user -> slug . '@' . $host,
    'aliases' => [$actor_uri],
    'links' => [
        [
            'rel' => 'self',
            'type' => 'application/activity+json',
            'href' => $actor_uri,
        ],
        [
            'rel' => 'http://webfinger.net/rel/profile-page',
            'type' => 'text/html',
            'href' => $actor_uri,
        ],
    ],
]);
