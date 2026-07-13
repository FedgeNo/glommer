<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

if (!Auth::canModerate()) {
    require __DIR__ . '/404.php';
    exit;
}

['rows' => $reports, 'hasMore' => $has_more] = Report::rowsForAdmin(20);

// needsMath so KaTeX loads: a reported post can contain math, and main.js runs
// render_math over each card (server-rendered here, and appended on scroll).
$page = Page::create('Reports', needsMath: true);

$page -> addContent(ReportList::fromRows($reports, $has_more));

$page -> send();
