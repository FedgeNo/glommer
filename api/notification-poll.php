<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$since_id = (int) ($_GET['sinceId'] ?? -1);

if ($since_id < 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$rows = Notification::rowsSince((int) Auth::id(), $since_id);

JSONResponse::success([
    'notifications' => Notification::rowsToPayload($rows),
]) -> send();
