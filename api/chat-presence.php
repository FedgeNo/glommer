<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$other_user_id = (int) ($payload['otherUserId'] ?? 0);

if ($other_user_id === 0 || $other_user_id === (int) $current_user -> userId) {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
}

// Leaving the page: clear the beat so the other side stops being told someone
// is there, rather than waiting out the window.
if (($payload['leaving'] ?? false) === true) {
    ChatPresence::leave((int) $current_user -> userId);

    JSONResponse::success(['otherUserPresent' => false]) -> send();
}

// A blocked pair has no thread to be present in - messages.php refuses the view
// outright, so this only ever catches a hand-made request.
if (Block::exists((int) $current_user -> userId, $other_user_id)) {
    JSONResponse::localizedError('unableToOpenThisConversation', 403) -> send();
}

// One round trip does both halves: beat for the caller, and answer whether the
// other person has this same thread open right now.
ChatPresence::enter((int) $current_user -> userId, $other_user_id);

JSONResponse::success([
    'otherUserPresent' => ChatPresence::isPresentWith($other_user_id, (int) $current_user -> userId),
]) -> send();
