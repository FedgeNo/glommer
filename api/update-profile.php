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

$title = trim((string) ($payload['title'] ?? ''));
$description = trim((string) ($payload['description'] ?? ''));

if (mb_strlen($title) > 50) {
    JSONResponse::error('Display name must be 50 characters or fewer.', 422) -> send();
}

if (mb_strlen($description) > 500) {
    JSONResponse::error('Bio must be 500 characters or fewer.', 422) -> send();
}

// The display name is stored as typed, empty string included - "no display
// name" is just an empty string, and the card/byline fall back to the @slug for
// it. A cleared bio is stored NULL and simply shows nothing.
$description_value = $description !== '' ? $description : null;

DB::run('
UPDATE `Users`
    SET `title` = ?, `description` = ?
    WHERE `userId` = ?
', 'ssi', $title, $description_value, $current_user -> userId);

JSONResponse::success(['title' => $title, 'description' => $description_value]) -> send();
