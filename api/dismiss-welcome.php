<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// Only an explicit yes puts the welcome away for good. Closing it without
// ticking the box is the browser's business alone and reaches no further than
// this request - which is why that case never gets here at all.
if (($payload['forGood'] ?? false) !== true) {
    JSONResponse::success(['welcomeDismissed' => false]) -> send();
}

DB::run('
UPDATE `Users`
    SET `welcomeDismissed` = 1
    WHERE `userId` = ?
', 'i', (int) Auth::id());

JSONResponse::success(['welcomeDismissed' => true]) -> send();
