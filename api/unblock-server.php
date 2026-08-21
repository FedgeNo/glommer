<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

Auth::requireLogin();

if (!Auth::canModerate()) {
    JSONResponse::localizedError('notAllowed', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$domain = trim((string) ($payload['domain'] ?? ''));

if ($domain === '') {
    JSONResponse::localizedError('invalidRequest', 422) -> send();
}

// Lifting a block does not restore what it severed. The follows it dropped are
// gone, and both sides have to follow again - which is the honest outcome, since
// neither server kept the relationship while the block stood.
RemoteServer::unblock($domain);

JSONResponse::success(['unblocked' => true]) -> send();
