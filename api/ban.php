<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$user_id = (int) ($payload['userId'] ?? 0);
$reason = trim((string) ($payload['reason'] ?? ''));

if ($user_id === 0 || $user_id === 1) {
    JSONResponse::localizedError('invalidTarget', 422) -> send();
}

// A ban always carries a reason (enforced in the UI dialog too) - it's shown to
// the user on the login form, so it can't be blank.
if ($reason === '') {
    JSONResponse::localizedError('aBanReasonIsRequired', 422) -> send();
}

$reason = mb_substr($reason, 0, 1000);

$target = User::load($user_id);

if ($target === null) {
    JSONResponse::localizedError('userNotFound', 404) -> send();
}

if ($target -> banned) {
    JSONResponse::localizedError('thatUserIsAlreadyBanned', 422) -> send();
}

$banned = 1;

DB::run('
UPDATE `Users`
    SET `banned` = ?, `banReason` = ?
    WHERE `userId` = ?
', 'isi', $banned, $reason, $user_id);

ModerationAction::log('ban', $user_id);

JSONResponse::success(['banned' => true]) -> send();
