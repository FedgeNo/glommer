<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
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

$api_key = trim((string) ($payload['openRouterAPIKey'] ?? ''));

// The API key is write-only: a blank field means "leave the stored key
// unchanged" (it's never rendered back into the form), so only overwrite it
// when an actual value is submitted. Clearing is therefore its own explicit
// flag (the form's "remove the stored key" checkbox) - a typed key wins over
// a simultaneously ticked clear, being the more deliberate act.
if ($api_key !== '') {
    Settings::set(OpenRouter::API_KEY_SETTING, $api_key);
} elseif (($payload['clearOpenRouterAPIKey'] ?? false) === true) {
    Settings::set(OpenRouter::API_KEY_SETTING, '');
}

Settings::set(OpenRouter::MODEL_SETTING, trim((string) ($payload['openRouterModel'] ?? '')));
Settings::set(OpenRouter::NEVER_SPEND_SETTING, ($payload['openRouterNeverSpend'] ?? false) ? '1' : '0');

JSONResponse::success(['saved' => true]) -> send();
