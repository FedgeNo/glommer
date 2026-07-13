<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$theme = (string) ($payload['theme'] ?? '');

$valid_themes = ['system', 'light', 'dark', 'sepia', 'midnight', 'sunset'];

if (!in_array($theme, $valid_themes, true)) {
    JSONResponse::error('Invalid theme', 422) -> send();
}

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `theme` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'si', $theme, $current_user -> userId);
mysqli_stmt_execute($stmt);

JSONResponse::success(['theme' => $theme]) -> send();
