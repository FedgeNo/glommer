<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

Notification::markSeen((int) Auth::id());

JSONResponse::success(['seen' => true]) -> send();
