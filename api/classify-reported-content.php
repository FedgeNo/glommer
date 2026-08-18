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

// Which post this is comes from the report row, never from the request - the
// same rule deletion follows, for the same reason: a moderator acts on what
// was reported, not on anything they can name.
$report = ReportManager::find($report_id);

if ($report === null) {
    JSONResponse::error('Report not found', 404) -> send();
}

if ($report -> type !== 'post') {
    JSONResponse::error('Only a post carries media to classify.', 422) -> send();
}

Post::classify((int) $report -> targetId, true);

// Followers elsewhere are holding the same post and rendering it unmarked, so
// the classification has to reach them too. Sent as the author, since it is
// their object that changed; a no-op for a post that came from another server,
// where the flag is theirs to set and ours only to honour.
$author = FediversePublisher::authorOf((int) $report -> targetId);

if ($author !== null) {
    $row = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) $report -> targetId);

    if ($row !== null) {
        $post = Post::fromRowWithItems($row);
        $post -> author = $author;

        FediversePublisher::updated($post, $author);
    }
}

// The moderator has acted on it, so it leaves the queue - same as a deletion.
ReportManager::delete($report_id);

ModerationAction::log('classifyReportedContent', null, $report -> type, (int) $report -> targetId, $report_id);

JSONResponse::success(['classified' => true]) -> send();
