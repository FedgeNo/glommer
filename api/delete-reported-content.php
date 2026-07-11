<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$report_id = (int) ($payload['reportId'] ?? 0);

if ($report_id === 0) {
    JSONResponse::error('Invalid report', 422) -> send();
}

// Resolve what to delete from the report row itself, so a moderator can only
// ever delete content something was actually reported for - never arbitrary
// client-supplied ids.
$report = Report::find($report_id);

if ($report === null) {
    JSONResponse::error('Report not found', 404) -> send();
}

if ($report['targetType'] === 'post') {
    Post::delete($report['targetId']);
} elseif ($report['targetType'] === 'message') {
    Message::delete($report['targetId']);
} else {
    JSONResponse::error('That report has no deletable content.', 422) -> send();
}

// Removing the content resolves the report, so clear it from the queue too.
Report::delete($report_id);

JSONResponse::success(['deleted' => true]) -> send();
