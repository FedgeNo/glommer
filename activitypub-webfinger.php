<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Same as the actor document: a discovery lookup from another server carries
// no cookie, so its throwaway session isn't left behind. A browser session
// (cookie present) is untouched.
if (!isset($_COOKIE[session_name()])) {
    session_destroy();
}

// Public - WebFinger (RFC 7033). This instance has exactly one ActivityPub
// identity (see ActivityPubKeys), so the only resource that ever resolves is
// that one fixed acct: address; anything else 404s.
$host = parse_url(ServerURL::absolute(''), PHP_URL_HOST);
$resource = is_string($_GET['resource'] ?? null) ? $_GET['resource'] : '';

// Compared case-insensitively: the host half of an acct: resource is a
// hostname, so a caller that normalises it differently is asking for the
// same account, not a missing one.
if (!ActivityPubKeys::isConfigured() || strcasecmp($resource, 'acct:glommer@' . $host) !== 0) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/jrd+json');
echo json_encode([
    'subject' => $resource,
    'links' => [
        [
            'rel' => 'self',
            'type' => 'application/activity+json',
            'href' => ServerURL::absolute('/activitypub/actor'),
        ],
    ],
], JSON_UNESCAPED_SLASHES);
