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

$payload = APIRequest::read(['reportId' => 'integer']);
$report_id = (int) ($payload['reportId'] ?? 0);

if ($report_id === 0) {
    JSONResponse::localizedError('invalidReport', 422) -> send();
}

// Resolve what to delete from the report row itself, so a moderator can only
// ever delete content something was actually reported for - never arbitrary
// client-supplied ids.
$report = ReportManager::find($report_id);

if ($report === null) {
    JSONResponse::localizedError('reportNotFound', 404) -> send();
}

if (!in_array($report -> type, ['post', 'message'], true)) {
    JSONResponse::localizedError('thatReportHasNoDeletableContent', 422) -> send();
}

if (!ReportManager::deleteContent($report_id)) {
    JSONResponse::localizedError('reportNotFound', 404) -> send();
}

JSONResponse::success(['deleted' => true]) -> send();
