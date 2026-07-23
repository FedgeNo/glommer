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

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$theme = (string) ($payload['theme'] ?? '');

$valid_themes = ['system', 'light', 'dark', 'sepia', 'midnight', 'sunset', 'rose', 'forest', 'ocean', 'lavender', 'gold', 'hacker'];

if (!in_array($theme, $valid_themes, true)) {
    JSONResponse::error('Invalid theme', 422) -> send();
}

DB::run('
UPDATE `Users`
    SET `theme` = ?
    WHERE `userId` = ?
', 'si', $theme, $current_user -> userId);

JSONResponse::success(['theme' => $theme]) -> send();
