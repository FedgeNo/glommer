<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = DB::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$skin_tone = (string) ($payload['skinTone'] ?? '');

// emoji-picker-element's skin tones are exactly 0 (default) through 5 -
// anything else stored here would come back through EmojiPickerAssets'
// init script and break the picker's preference restore.
if (!preg_match('/^[0-5]$/', $skin_tone)) {
    JSONResponse::error('Invalid skin tone', 422) -> send();
}

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `skinTone` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'si', $skin_tone, $current_user -> userId);
mysqli_stmt_execute($stmt);

JSONResponse::success(['skinTone' => $skin_tone]) -> send();
