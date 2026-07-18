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

// How many notifications the client already shows - the next page starts
// there.
$offset = max(0, (int) ($payload['offset'] ?? 0));

$fetch_limit = NotificationList::PAGE_SIZE + 1;

$rows = DB::rows('
SELECT `n`.*, `u`.`slug` AS `actorUsername`, `u`.`title` AS `actorDisplayName`, `u`.`hasAvatar` AS `actorHasAvatar`
    FROM `Notifications` `n`
    JOIN `Users` `u` ON `u`.`userId` = `n`.`actorId`
    WHERE `n`.`userId` = ?
    ORDER BY `n`.`notificationId` DESC
    LIMIT ? OFFSET ?
', 'Notification', 'iii', (int) $current_user -> userId, $fetch_limit, $offset);

$has_more = count($rows) > NotificationList::PAGE_SIZE;

if ($has_more) {
    array_pop($rows);
}

JSONResponse::success([
    'notifications' => Notification::rowsToPayload($rows),
    'hasMore' => $has_more,
]) -> send();
