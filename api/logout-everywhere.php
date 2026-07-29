<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$userId = (int) Auth::user() -> userId;

// Remove all persistent "Remember me" tokens for this user.
DB::run('DELETE FROM `RememberTokens` WHERE `userId` = ?', 'i', $userId);

// Invalidate every active session by bumping the session version.
User::bumpSessionVersion($userId);

JSONResponse::success(['message' => 'All sessions ended.']) -> send();
