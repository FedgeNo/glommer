<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Admin only
if (Auth::id() !== 1) {
    JSONResponse::error('Forbidden', 403)->send();
}

// Uses a type that doesn't match any case, so the default text
// "Admin did something" (or whatever the admin's display name is) is shown.
Notification::create(1, 1, 'test_notification', null, true);

JSONResponse::success(['message' => 'Test notification sent.'])->send();

