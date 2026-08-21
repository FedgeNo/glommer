<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// A GET must never end every session on its own: init.php's centralized CSRF
// check only covers POST, so without this a third-party page could force-log
// a victim out of all their devices with a plain cross-site GET. Same guard
// the other GET-reachable mutators (logout, resend-verification) carry.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

$user_id = (int) Auth::user() -> userId;

// Remove all persistent "Remember me" tokens for this user.
DB::run('
DELETE
    FROM `RememberTokens`
    WHERE `userId` = ?
', 'i', $user_id);

// Invalidate every active session by bumping the session version.
User::bumpSessionVersion($user_id);

JSONResponse::success(['message' => 'All sessions ended.']) -> send();
