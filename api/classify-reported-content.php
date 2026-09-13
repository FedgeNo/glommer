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

// Which post this is comes from the report row, never from the request - the
// same rule deletion follows, for the same reason: a moderator acts on what
// was reported, not on anything they can name.
$report = ReportManager::find($report_id);

if ($report === null) {
    JSONResponse::localizedError('reportNotFound', 404) -> send();
}

if ($report -> type !== 'post') {
    JSONResponse::localizedError('onlyAPostCarriesMediaToClassify', 422) -> send();
}

$classified = ReportManager::resolve($report_id, 'classifyReportedContent', static function (ReportData $report): void {
    Post::classify((int) $report -> targetId, true);

    // Queue the author's update in the same transaction as the classification.
    // Delivery remains the worker's job after commit.
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
});

if (!$classified) {
    JSONResponse::localizedError('reportNotFound', 404) -> send();
}

JSONResponse::success(['classified' => true]) -> send();
