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

$before_notification_id = (int) ($payload['beforeNotificationId'] ?? 0);

if ($before_notification_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

['rows' => $rows, 'hasMore' => $has_more] = Notification::rowsForUser($current_user -> userId, 20, $before_notification_id);

JSONResponse::success([
    'notifications' => Notification::rowsToPayload($rows),
    'hasMore' => $has_more,
]) -> send();
