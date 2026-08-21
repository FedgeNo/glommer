<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

// Site-wide settings are the primary admin's alone, the same gate as every other
// admin-only write.
if (Auth::id() !== 1) {
    JSONResponse::localizedError('forbidden', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

Settings::set(MapTiles::URL_SETTING, trim((string) ($payload['mapTileURL'] ?? '')));
Settings::set(MapTiles::KEY_SETTING, trim((string) ($payload['mapTileAPIKey'] ?? '')));
Settings::set(MapTiles::ATTRIBUTION_SETTING, trim((string) ($payload['mapTileAttribution'] ?? '')));

JSONResponse::success(['saved' => true]) -> send();
