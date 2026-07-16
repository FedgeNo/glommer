<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// Public, like the rest of the Help section - no login required.
$query = trim((string) ($payload['q'] ?? ''));

// Empty query is the browse view: every article, in category order, which
// help.js groups under category headings. A real query returns ranked matches.
$articles = $query === '' ? HelpContent::all() : HelpContent::search($query);

JSONResponse::success([
    'grouped' => $query === '',
    'articles' => array_map(static fn (HelpArticle $article): array => $article -> toPayload(), $articles),
]) -> send();
