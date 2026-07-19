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

// Checked once up front rather than per handle, which would otherwise repeat
// the same setup error back for every entry in the list.
if (!ActivityPubKeys::isConfigured()) {
    JSONResponse::error('Fediverse support is not set up on this server yet.', 503) -> send();
}

$handles = FediverseHandle::parseAll($raw);

if ($handles === []) {
    JSONResponse::error('No valid Fediverse handles found (expected user@domain).', 422) -> send();
}

RateLimiter::recordAttempt($rate_key);

// Following is three network round trips per handle (WebFinger, the actor
// document, then the signed delivery), any of which can sit at its timeout
// against an unresponsive server. Both bounds below exist so a long list
// can't run the request past PHP's execution limit, which would kill it
// mid-loop - follows already done, no response, and no way for the person to
// tell what landed. Whatever isn't reached is reported back rather than
// silently dropped, so re-submitting picks up the rest.
$max_handles_per_submit = 25;
$time_budget_seconds = 15.0;
$started_at = microtime(true);

$results = [];
$unprocessed = [];

foreach ($handles as $handle) {
    if (count($results) >= $max_handles_per_submit || microtime(true) - $started_at > $time_budget_seconds) {
        $unprocessed[] = $handle['handle'];

        continue;
    }

    $results[] = RemoteFollow::create($current_user -> userId, $handle['user'], $handle['domain']);
}

JSONResponse::success(['results' => $results, 'unprocessed' => $unprocessed]) -> send();
