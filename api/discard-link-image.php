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

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$seed = (string) ($payload['seed'] ?? '');

// The name carries who staged the file, so this refuses one belonging to
// somebody else rather than deleting whatever it is handed.
if (!StagedUploadSeed::belongsTo($seed, (int) Auth::id())) {
    JSONResponse::localizedError('invalidSeed', 422) -> send();
}

UploadProcessor::delete($seed, 'ImageItem', null);

JSONResponse::success(['discarded' => true]) -> send();
