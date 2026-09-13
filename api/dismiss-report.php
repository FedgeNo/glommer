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
$report_id = (int) ($payload['reportId'] ?? 0);

if ($report_id === 0) {
    JSONResponse::localizedError('invalidReport', 422) -> send();
}

// The flag, queue transition and audit record either all commit or all retry.
$dismissed = ReportManager::resolve($report_id, 'dismissReport', static function (ReportData $report): void {
    ReportManager::markContentDismissed((string) $report -> type, (int) $report -> targetId);
});

if (!$dismissed) {
    JSONResponse::localizedError('reportNotFound', 404) -> send();
}

JSONResponse::success(['dismissed' => true]) -> send();
