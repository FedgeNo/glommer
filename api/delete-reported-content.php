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

// Resolve what to delete from the report row itself, so a moderator can only
// ever delete content something was actually reported for - never arbitrary
// client-supplied ids.
$report = ReportManager::find($report_id);

if ($report === null) {
    JSONResponse::error('Report not found', 404) -> send();
}

if ($report -> type === 'post') {
    // A moderator's deletion federates the same as the author's own: the post
    // is equally gone either way, and followers elsewhere are holding the same
    // copy. Read before the row, while the URI can still be built, and sent as
    // the author since it is their object being withdrawn.
    $object_uri = FediversePublisher::objectURIFor((int) $report -> targetId);
    $author = FediversePublisher::authorOf((int) $report -> targetId);

    Post::delete((int) $report -> targetId);

    if ($object_uri !== null && $author !== null) {
        FediversePublisher::deleted($object_uri, $author);
    }
} elseif ($report -> type === 'message') {
    Message::delete((int) $report -> targetId);
} else {
    JSONResponse::error('That report has no deletable content.', 422) -> send();
}

// Removing the content resolves the report, so clear it from the queue too.
ReportManager::delete($report_id);

ModerationAction::log('deleteReportedContent', null, $report -> type, (int) $report -> targetId, $report_id);

JSONResponse::success(['deleted' => true]) -> send();
