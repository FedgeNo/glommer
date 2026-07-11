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

Report::delete($report_id);

JSONResponse::success(['dismissed' => true]) -> send();
