<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

// Public, read-only: the map shows everyone's geotagged posts. The listing is
// fixed and capped rather than a user-controlled search, but it still costs a
// 5000-row join per call and needs no login, so it's paced per client to keep
// it from being used as a cheap database-load lever.
$rate_key = 'map-posts:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
    JSONResponse::error('Too many requests. Please slow down.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);
$rows = DB::rows('
SELECT `p`.`postId`, `p`.`latitude`, `p`.`longitude`, `p`.`title`, `u`.`slug`, `u`.`title` AS `authorName`
    FROM `Posts` `p`
    JOIN `Users` `u` ON `u`.`userId` = `p`.`userId`
    WHERE `p`.`latitude` IS NOT NULL
    AND `p`.`longitude` IS NOT NULL
    AND `u`.`banned` = ?
    ORDER BY `p`.`createdAt` DESC
    LIMIT 5000
', \stdClass::class, 'i', 0);

$posts = [];

foreach ($rows as $row) {
    $posts[] = [
        'postId' => (int) $row -> postId,
        'latitude' => (float) $row -> latitude,
        'longitude' => (float) $row -> longitude,
        'title' => $row -> title,
        'authorName' => $row -> authorName !== null && $row -> authorName !== '' ? $row -> authorName : $row -> slug,
        'url' => ServerURL::absolute('/users/' . $row -> slug . '/' . (int) $row -> postId),
    ];
}

JSONResponse::success(['posts' => $posts]) -> send();
