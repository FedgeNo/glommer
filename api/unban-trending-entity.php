<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$entity_type = trim((string) ($payload['entityType'] ?? ''));
$entity_value = trim((string) ($payload['entityValue'] ?? ''));

if ($entity_type === '' || $entity_value === '') {
    JSONResponse::error('Invalid target', 422) -> send();
}

if (!Trending::isBanned($entity_type, $entity_value)) {
    JSONResponse::error('That entity is not banned', 422) -> send();
}

Trending::unban($entity_type, $entity_value, (int) Auth::id());

JSONResponse::success(['unbanned' => true]) -> send();
