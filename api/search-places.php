<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

// Auth-gated like every other DB-backed search - an open LIKE endpoint over
// a 235,000-row table is a DDoS hazard, public though the nearby page is.
Auth::requireLogin();

$rate_key = 'search-places:' . Auth::id();

if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
    JSONResponse::error('Too many searches in a short time.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

$payload = json_decode((string) file_get_contents('php://input'), true);

$places = [];

foreach (Place::suggest((string) ($payload['q'] ?? '')) as $place) {
    $places[] = [
        'label' => $place -> label(),
        'latitude' => (float) $place -> latitude,
        'longitude' => (float) $place -> longitude,
    ];
}

JSONResponse::success(['places' => $places]) -> send();
