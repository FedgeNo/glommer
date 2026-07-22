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

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$query = trim((string) ($payload['q'] ?? ''));
$offset = max(0, (int) ($payload['offset'] ?? 0));

// An empty query hands back the ranked suggestions (mutual-friend count,
// falling back to random) the list inherits; a query hands back its matches.
// Either way the list owns the query and settles its own pagination.
$results = new UserSearchList([
    'query' => $query,
    'offset' => $offset,
]) -> toJSON();

$users = [];

foreach ($results['items'] as $candidate) {
    $users[] = OtherUser::payloadFor($candidate, $current_user);
}

JSONResponse::success([
    'users' => $users,
    'hasMore' => $results['hasMore'],
]) -> send();
