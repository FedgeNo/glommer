<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

// This endpoint mutates state but reads no POST fields, so without an
// explicit method requirement a plain GET link would trigger it - and
// init.php's centralized CSRF check only covers POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

Notification::markSeen((int) Auth::id());

JSONResponse::success(['seen' => true]) -> send();
