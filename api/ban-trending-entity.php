<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$entity_type = trim((string) ($payload['entityType'] ?? ''));
$entity_value = trim((string) ($payload['entityValue'] ?? ''));
$reason = trim((string) ($payload['reason'] ?? ''));

if ($entity_type === '' || $entity_value === '') {
    JSONResponse::error('Invalid target', 422) -> send();
}

// A ban always carries a reason, same rule api/ban.php enforces for user bans.
if ($reason === '') {
    JSONResponse::error('A ban reason is required.', 422) -> send();
}

$reason = mb_substr($reason, 0, 1000);

Trending::ban($entity_type, $entity_value, (int) Auth::id(), $reason);

JSONResponse::success(['banned' => true]) -> send();
