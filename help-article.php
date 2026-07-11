<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Fully public, like the rest of the Help section.
$slug = (string) ($_GET['slug'] ?? '');
$article = HelpContent::find($slug);

if ($article === null) {
    require __DIR__ . '/404.php';
    exit;
}

$page = Page::create($article -> title, $article -> summary);

$page -> addContents($article);

$page -> send();
