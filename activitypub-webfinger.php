<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - WebFinger (RFC 7033). This instance has exactly one ActivityPub
// identity (see ActivityPubKeys), so the only resource that ever resolves is
// that one fixed acct: address; anything else 404s.
$host = parse_url(ServerURL::absolute(''), PHP_URL_HOST);
$resource = $_GET['resource'] ?? '';

if (!ActivityPubKeys::isConfigured() || $resource !== 'acct:glommer@' . $host) {
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
