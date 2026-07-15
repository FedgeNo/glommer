<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

Settings::set(SitePolicy::TERMS_SETTING, trim((string) ($payload[SitePolicy::TERMS_SETTING] ?? '')));

JSONResponse::success(['saved' => true]) -> send();
