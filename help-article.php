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

$page = new Page(['title' => $article -> title, 'description' => $article -> summary]);

$page -> addContent($article);

$page -> send();
