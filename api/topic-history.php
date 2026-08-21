<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// Public, like the page it pages: /topics/ needs no account to read. Paced per
// client for the same reason its neighbours are - an endpoint anybody can call
// without signing in is one anybody can call as fast as they like.
$rate_key = 'topic-history:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
    JSONResponse::localizedError('tooManyRequestsPleaseSlowDown', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

// The kind, named the way its address names it. Refused rather than defaulted:
// a request for a kind that does not exist is a mistake, and answering it with
// somebody else's list would hide that.
$type = EntityType::fromSlug(strtolower(trim((string) ($payload['entityType'] ?? ''))));

if ($type === null) {
    JSONResponse::localizedError('unknownTopicType', 404) -> send();
}

// How many chips the client already shows - the next page starts there.
$offset = max(0, (int) ($payload['offset'] ?? 0));

$page = new PopularEntityList(['type' => $type, 'offset' => $offset]) -> toJSON();

JSONResponse::success($page) -> send();
