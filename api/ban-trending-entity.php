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
$reason = trim((string) ($payload['reason'] ?? ''));

// entityType must be one the pipeline actually produces, and entityValue must
// fit its varchar(255) column - reject anything else as a 422 up front rather
// than letting an over-length value throw a data-truncation 500 on insert.
if (
    $entity_value === ''
    || mb_strlen($entity_value) > 255
    || !in_array($entity_type, EntityExtractor::ENTITY_TYPES, true)
) {
    JSONResponse::error('Invalid target', 422) -> send();
}

// A ban always carries a reason, same rule api/ban.php enforces for user bans.
if ($reason === '') {
    JSONResponse::error('A ban reason is required.', 422) -> send();
}

$reason = mb_substr($reason, 0, 1000);

Trending::ban($entity_type, $entity_value, (int) Auth::id(), $reason);

JSONResponse::success(['banned' => true]) -> send();
