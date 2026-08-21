<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

// A topic page is public but this is not: it spends a model call, and an
// endpoint that does that for anybody who asks is a bill waiting to be run up
// by a crawler walking every topic on the site. Somebody signed in reading a
// page is the case it exists for.
if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$type = strtolower(trim((string) ($payload['type'] ?? '')));
$slug = mb_strtolower(trim((string) ($payload['slug'] ?? '')));

if (!EntityType::isKnown($type) || $slug === '') {
    JSONResponse::localizedError('notATopic', 404) -> send();
}

// Only for something that has actually trended - the same thing the page
// itself requires, so this cannot be used to write a paragraph about any
// string somebody cares to post.
$entity = Entity::load($type, $slug);

if ($entity === null) {
    JSONResponse::localizedError('notATopic', 404) -> send();
}

// Already written while this reader was on their way here - another tab, the
// timer, or somebody else opening the same page a moment earlier.
$existing = TopicSummary::for($type, $slug);

if ($existing !== null) {
    JSONResponse::success(['summary' => $existing]) -> send();
}

// A model call each, so one reader cannot walk the whole list of topics and
// spend one per page. Keyed on the reader rather than the topic: the topic is
// written once and then answered from storage above.
$rate_key = 'topic-summary:' . Auth::id();

if (RateLimiter::tooManyAttempts($rate_key, 10, 600)) {
    JSONResponse::localizedError('giveThatAMoment', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

JSONResponse::success(['summary' => TopicSummary::write($type, $slug, (string) $entity -> title)]) -> send();
