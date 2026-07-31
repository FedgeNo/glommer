<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - NodeInfo discovery. Relay and directory software reads this to find
// out what a server runs before deciding how to talk to it, and the Fediverse's
// own census tools use it. Two documents: this one only points at the other.
$version = (string) ($_GET['schema'] ?? '');

if ($version === '2.0') {
    // Counts are of local members and their own posts - a shadow row for a
    // remote account is not a member here, and a post that arrived from
    // elsewhere is not ours to count.
    ActivityPubResponse::discardAnonymousSession();

    header('Content-Type: application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.0#"');

    echo json_encode([
        'version' => '2.0',
        'software' => ['name' => 'glommer', 'version' => GLOMMER_VERSION],
        'protocols' => ['activitypub'],
        'services' => ['inbound' => [], 'outbound' => []],
        'openRegistrations' => true,
        'usage' => [
            'users' => ['total' => NodeInfo::memberCount()],
            'localPosts' => NodeInfo::localPostCount(),
        ],
        'metadata' => ['nodeName' => (string) Config::get('siteTitle')],
    ], JSON_UNESCAPED_SLASHES);

    exit;
}

header('Content-Type: application/json');
ActivityPubResponse::send([
    'links' => [
        [
            'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
            'href' => ServerURL::absolute('/nodeinfo/2.0'),
        ],
    ],
]);
