<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

if (!Auth::canModerate()) {
    JSONResponse::error('Not allowed', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$domain = trim((string) ($payload['domain'] ?? ''));
$reason = trim((string) ($payload['reason'] ?? ''));

// Refusing our own host: blocking it would defederate the site from itself,
// and every outbound request would start failing silently.
if (ActivityPubActor::isLocalActorURI('https://' . $domain . '/')) {
    JSONResponse::error('That is this server.', 422) -> send();
}

$blocked = BlockedDomain::block($domain, $reason !== '' ? $reason : null, (int) Auth::id());

// Null means the entry was not shaped like a hostname. Said plainly rather than
// accepted quietly, so a typo does not look like a block that is in force.
if ($blocked === null) {
    JSONResponse::error('That does not look like a server name.', 422) -> send();
}

JSONResponse::success(['domain' => $blocked]) -> send();
