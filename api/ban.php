<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$mysqli = Database::connection();
$payload = json_decode((string) file_get_contents('php://input'), true);
$user_id = (int) ($payload['userId'] ?? 0);

if ($user_id === 0 || $user_id === 1) {
    JSONResponse::error('Invalid target', 422) -> send();
}

$banned = 1;

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `banned` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'ii', $banned, $user_id);
mysqli_stmt_execute($stmt);

JSONResponse::success(['banned' => true]) -> send();
