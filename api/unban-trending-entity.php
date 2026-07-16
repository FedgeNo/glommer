<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::error('Not authorized', 403) -> send();
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
    JSONResponse::error('Invalid target', 422) -> send();
}

if (!Trending::isBanned($entity_type, $entity_value)) {
    JSONResponse::error('That entity is not banned', 422) -> send();
}

Trending::unban($entity_type, $entity_value);

JSONResponse::success(['unbanned' => true]) -> send();
