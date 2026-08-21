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

// Control characters render as nothing and cannot be written to XML at all,
// so a display name carrying one would break the feeds these appear in.
$title = ControlCharacters::strip(trim((string) ($payload['title'] ?? '')));
$description = ControlCharacters::strip(trim((string) ($payload['description'] ?? '')));

if (mb_strlen($title) > 50) {
    JSONResponse::localizedError('displayNameMustBe50CharactersOrFewer', 422) -> send();
}

if (mb_strlen($description) > 500) {
    JSONResponse::localizedError('bioMustBe500CharactersOrFewer', 422) -> send();
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

// Followers hold a copy of the actor document; a change here makes theirs
// stale until they are told.
$current_user -> title = $title;
$current_user -> description = $description_value;
FediversePublisher::profileUpdated($current_user);

JSONResponse::success(['title' => $title, 'description' => $description_value]) -> send();
