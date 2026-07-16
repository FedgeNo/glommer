<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$mysqli = Database::connection();
$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$user_id = (int) ($payload['userId'] ?? 0);
$reason = trim((string) ($payload['reason'] ?? ''));

if ($user_id === 0 || $user_id === 1) {
    JSONResponse::error('Invalid target', 422) -> send();
}

// A ban always carries a reason (enforced in the UI dialog too) - it's shown to
// the user on the login form, so it can't be blank.
if ($reason === '') {
    JSONResponse::error('A ban reason is required.', 422) -> send();
}

$reason = mb_substr($reason, 0, 1000);

$target = User::load($user_id);

if ($target === null) {
    JSONResponse::error('User not found', 404) -> send();
}

if ($target -> banned) {
    JSONResponse::error('That user is already banned', 422) -> send();
}

$banned = 1;

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `banned` = ?, `banReason` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'isi', $banned, $reason, $user_id);
mysqli_stmt_execute($stmt);

ModerationAction::log('ban', $user_id);

JSONResponse::success(['banned' => true]) -> send();
