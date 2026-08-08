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

// A handle names somebody who may be on a server this one has never spoken
// to, so searching for one sends this server to go and ask. Done before the
// search rather than after it, so whoever it finds is already an ordinary row
// by the time the search runs - and only on the first page, since scrolling
// further into the results is not a new thing to look for.
$found_remotely = $offset === 0 ? FediverseLookup::find($query) : null;

// An empty query hands back the ranked suggestions (mutual-friend count,
// falling back to random) the list inherits; a query hands back its matches.
// Either way the list owns the query and settles its own pagination.
$results = new UserSearchList([
    'query' => $query,
    'offset' => $offset,
]) -> toJSON();

$users = [];

// Whoever the handle named leads, rather than being left to place among the
// matches: somebody who typed an address in full has said exactly who they
// mean, and full-text ranking is no way to answer that.
if ($found_remotely !== null && (int) $found_remotely -> userId !== (int) $current_user -> userId) {
    $users[] = OtherUser::payloadFor($found_remotely, $current_user);
}

foreach ($results['items'] as $candidate) {
    if ($found_remotely !== null && (int) $candidate -> userId === (int) $found_remotely -> userId) {
        continue;
    }

    $users[] = OtherUser::payloadFor($candidate, $current_user);
}

JSONResponse::success([
    'users' => $users,
    'hasMore' => $results['hasMore'],
]) -> send();
