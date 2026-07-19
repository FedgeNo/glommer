<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$rate_key = 'follow-remote:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 20, 3600)) {
    JSONResponse::error('Too many follow requests. Please wait a bit and try again.', 429) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$raw = (string) ($payload['handles'] ?? '');

if (strlen($raw) > 8192) {
    JSONResponse::error('That list is too long.', 422) -> send();
}

$handles = FediverseHandle::parseAll($raw);

if ($handles === []) {
    JSONResponse::error('No valid Fediverse handles found (expected user@domain).', 422) -> send();
}

// A paste of a genuinely huge list shouldn't fire off dozens of outbound
// network requests in a single request cycle - cap how many this one submit
// actually acts on; the rest can just be pasted again.
$handles = array_slice($handles, 0, 25);

RateLimiter::recordAttempt($rate_key);

$results = [];

foreach ($handles as $handle) {
    $results[] = RemoteFollow::create($current_user -> userId, $handle['user'], $handle['domain']);
}

JSONResponse::success(['results' => $results]) -> send();
