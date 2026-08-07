<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

$payload = json_decode((string) file_get_contents('php://input'), true);

// Scoped to the owner inside the DELETE itself - someone else's id is simply
// not matched, same as it not existing.
StagedPost::discard((int) ($payload['stagedPostId'] ?? 0), (int) Auth::id());

JSONResponse::success(['discarded' => true]) -> send();
