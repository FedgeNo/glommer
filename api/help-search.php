<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

// Public, like the rest of the Help section - no login required.
$query = $payload['q'] ?? '';

if (!is_string($query)) {
    JSONResponse::localizedError('malformedRequest', 422) -> send();
}

$query = trim($query);

// Empty query is the browse view: every article, in category order, which
// HelpSearch.js groups under category headings. A real query returns ranked matches.
$articles = $query === '' ? HelpContent::all() : HelpContent::search($query);

JSONResponse::success([
    'grouped' => $query === '',
    'articles' => array_map(static fn (HelpArticle $article): array => $article -> toPayload(), $articles),
]) -> send();
