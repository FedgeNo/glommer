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

$query = trim((string) ($payload['q'] ?? ''));

if ($query === '') {
    JSONResponse::error('Missing query', 422) -> send();
}

// Escape LIKE wildcards so a literal % or _ in the query doesn't match everything.
$like = '%' . addcslashes($query, '\\%_') . '%';
$banned = 1;
$limit = 20;

$users = DB::rows('
SELECT *
    FROM `Users`
    WHERE (`slug` LIKE ? OR `title` LIKE ?) AND `banned` = ?
    ORDER BY `userId` DESC
    LIMIT ?
', 'User', 'ssii', $like, $like, $banned, $limit);

$payloads = [];

foreach ($users as $user) {
    $payloads[] = BannedUser::payloadFor($user);
}

JSONResponse::success(['items' => $payloads]) -> send();
