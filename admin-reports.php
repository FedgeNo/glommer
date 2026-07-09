<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

if (Auth::id() !== 1) {
    require __DIR__ . '/404.php';
    exit;
}

$reports = Report::rowsForAdmin();

$page = Page::create('Reports');

if ($reports === []) {
    $page -> addContents(new Notice('No reports.'));
} else {
    foreach ($reports as $report) {
        $page -> addContents(ReportCard::fromRow($report));
    }
}

$page -> send();
