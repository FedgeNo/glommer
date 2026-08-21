<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$entity_type = trim((string) ($payload['entityType'] ?? ''));
$entity_value = trim((string) ($payload['entityValue'] ?? ''));

if (
    $entity_value === ''
    || mb_strlen($entity_value) > 255
    || !in_array($entity_type, EntityExtractor::ENTITY_TYPES, true)
) {
    JSONResponse::localizedError('invalidTarget', 422) -> send();
}

if (!EntityRanker::isBanned($entity_type, $entity_value)) {
    JSONResponse::localizedError('thatEntityIsNotBanned', 422) -> send();
}

EntityRanker::unban($entity_type, $entity_value);

JSONResponse::success(['unbanned' => true]) -> send();
