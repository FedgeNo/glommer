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

$payload = APIRequest::read(['userId' => 'integer']);
$user_id = (int) ($payload['userId'] ?? 0);

if ($user_id === 0) {
    JSONResponse::localizedError('invalidTarget', 422) -> send();
}

$target = User::load($user_id);

if ($target === null) {
    JSONResponse::localizedError('userNotFound', 404) -> send();
}

if (!$target -> banned) {
    JSONResponse::localizedError('thatUserIsNotBanned', 422) -> send();
}

$not_banned = 0;

DB::transaction(static function () use ($not_banned, $user_id): void {
    DB::run('
UPDATE `Users`
    SET `banned` = ?
    WHERE `userId` = ?
', 'ii', $not_banned, $user_id);

    ModerationAction::log('unban', $user_id);
});

JSONResponse::success(['unbanned' => true]) -> send();
