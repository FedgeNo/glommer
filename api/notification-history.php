<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$before_notification_id = (int) ($_GET['beforeNotificationId'] ?? 0);

if ($before_notification_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

['rows' => $rows, 'hasMore' => $has_more] = Notification::rowsForUser($current_user -> userId, 20, $before_notification_id);

JSONResponse::success([
    'notifications' => Notification::rowsToPayload($rows),
    'hasMore' => $has_more,
]) -> send();
