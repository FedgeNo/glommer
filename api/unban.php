<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$mysqli = Database::connection();
$payload = json_decode((string) file_get_contents('php://input'), true);
$user_id = (int) ($payload['userId'] ?? 0);

if ($user_id === 0) {
    JSONResponse::error('Invalid target', 422) -> send();
}

$target = User::load($user_id);

if ($target === null) {
    JSONResponse::error('User not found', 404) -> send();
}

if (!$target -> banned) {
    JSONResponse::error('That user is not banned', 422) -> send();
}

$not_banned = 0;

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `banned` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'ii', $not_banned, $user_id);
mysqli_stmt_execute($stmt);

ModerationAction::log('unban', $user_id);

JSONResponse::success(['unbanned' => true]) -> send();
