<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

// Public, like the rest of the Help section - no login required.
$query = trim((string) ($_GET['q'] ?? ''));

// Empty query is the browse view: every article, in category order, which
// help.js groups under category headings. A real query returns ranked matches.
$articles = $query === '' ? HelpContent::all() : HelpContent::search($query);

JSONResponse::success([
    'grouped' => $query === '',
    'articles' => array_map(static fn (HelpArticle $article): array => $article -> toPayload(), $articles),
]) -> send();
