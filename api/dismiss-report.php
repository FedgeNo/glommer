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
$report_id = (int) ($payload['reportId'] ?? 0);

if ($report_id === 0) {
    JSONResponse::error('Invalid report', 422) -> send();
}

// Load the report before deleting it so the audit log records what was
// dismissed, not just an id that no longer resolves to anything.
$report = Report::find($report_id);

if ($report === null) {
    JSONResponse::error('Report not found', 404) -> send();
}

// Flag the content so it can't just be reported again the moment the report
// leaves the queue (posts/messages only - a user has no such flag).
Report::markContentDismissed($report['targetType'], (int) $report['targetId']);

Report::delete($report_id);

ModerationAction::log('dismissReport', null, $report['targetType'], (int) $report['targetId'], $report_id);

JSONResponse::success(['dismissed' => true]) -> send();
