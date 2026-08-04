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

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// Read as a plain yes or no rather than trusted as given: anything that is not
// an explicit true leaves the cover in place, which is the safe direction for a
// setting whose whole purpose is to stop media arriving unasked.
$show = ($payload['showSensitiveMedia'] ?? false) === true ? 1 : 0;

DB::run('
UPDATE `Users`
    SET `showSensitiveMedia` = ?
    WHERE `userId` = ?
', 'ii', $show, $current_user -> userId);

JSONResponse::success(['showSensitiveMedia' => $show === 1]) -> send();
