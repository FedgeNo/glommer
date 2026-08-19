<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::error('Not authorized', 403) -> send();
}

// Moderators are trusted and still get the pace every other search here has:
// a wedged script in an open tab is the usual way one of these ends up in a
// loop, and it costs the same whoever is signed in. After the authorization
// check, so somebody with no business here cannot spend the budget of
// somebody who has.
if (SearchRateLimiter::tooManyAttempts('banned-users', (int) Auth::id())) {
    JSONResponse::error('Too many searches. Please slow down.', 429) -> send();
}

$query = trim((string) ($payload['q'] ?? ''));
$offset = max(0, (int) ($payload['offset'] ?? 0));

if ($query === '') {
    JSONResponse::error('Missing query', 422) -> send();
}

$results = new BannedUserSearchList([
    'query' => $query,
    'offset' => $offset,
]) -> toJSON();

$payloads = [];

foreach ($results['items'] as $user) {
    $payloads[] = BannedUser::payloadFor($user);
}

JSONResponse::success([
    'items' => $payloads,
    'hasMore' => $results['hasMore'],
]) -> send();
