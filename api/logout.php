<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// A GET must never log anyone out on its own: that would let a third-party
// page force-log-out a victim (a plain GET link/image), and init.php's
// centralized CSRF check only covers POST requests. Same guard the other
// GET-reachable mutators (resend-verification, mark-notifications-seen)
// already carry.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

RememberToken::forget();
Auth::logout();

JSONResponse::success(['loggedOut' => true]) -> send();
