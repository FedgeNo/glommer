<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// Blank is a real answer here - it puts the shipped wording back, rather than
// leaving the stored text alone (see EmailDigest::paragraph()).
Settings::set(EmailDigest::PARAGRAPH_SETTING, trim((string) ($payload[EmailDigest::PARAGRAPH_SETTING] ?? '')));

JSONResponse::success(['saved' => true]) -> send();
