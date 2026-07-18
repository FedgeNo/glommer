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

// How many banned-user cards the client already shows - the next page starts
// there.
$offset = max(0, (int) ($payload['offset'] ?? 0));
$limit = BannedUserList::PAGE_SIZE;

$items = BannedUserList::fetch($limit + 1, $offset);
$has_more = count($items) > $limit;

if ($has_more) {
    array_pop($items);
}

$payloads = [];

foreach ($items as $item) {
    $payloads[] = BannedUser::payloadFor($item);
}

JSONResponse::success([
    'items' => $payloads,
    'hasMore' => $has_more,
]) -> send();
