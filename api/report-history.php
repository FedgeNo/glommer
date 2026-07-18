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

if (!Auth::canModerate()) {
    JSONResponse::error('Forbidden', 403) -> send();
}

// How many report cards the client already shows - the next page starts
// there.
$offset = max(0, (int) ($payload['offset'] ?? 0));

['rows' => $rows, 'hasMore' => $has_more] = Report::rowsForAdmin(20, $offset);

JSONResponse::success([
    'reports' => ReportCard::rowsToPayload($rows),
    'hasMore' => $has_more,
]) -> send();
