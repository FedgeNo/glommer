<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

if (!Auth::canModerate()) {
    JSONResponse::error('Forbidden', 403) -> send();
}

$before_report_id = (int) ($_GET['beforeReportId'] ?? 0);

if ($before_report_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

['rows' => $rows, 'hasMore' => $has_more] = Report::rowsForAdmin(20, $before_report_id);

JSONResponse::success([
    'reports' => ReportCard::rowsToPayload($rows),
    'hasMore' => $has_more,
]) -> send();
