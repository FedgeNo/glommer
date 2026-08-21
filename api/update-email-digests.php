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

// Read as a plain yes or no: anything that is not an explicit true stops the
// mail, which is the safe direction for a setting about sending someone email.
$wanted = ($payload['emailDigests'] ?? false) === true;

EmailDigest::setEnabled((int) $current_user -> userId, $wanted);

JSONResponse::success(['emailDigests' => $wanted]) -> send();
