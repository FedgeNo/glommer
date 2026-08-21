<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

Auth::requireLogin();

$payload = json_decode((string) file_get_contents('php://input'), true);

// Scoped to the member inside the DELETE - an endpoint belonging to someone
// else is simply not matched.
DB::run('
DELETE
    FROM `PushSubscriptions`
    WHERE `endpoint` = ? AND `userId` = ?
', 'si', trim((string) ($payload['endpoint'] ?? '')), (int) Auth::id());

JSONResponse::success(['unsubscribed' => true]) -> send();
