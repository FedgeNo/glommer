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
// Driven from PostLocations: it holds only the posts that actually have a
// location, so this reads a small table and looks each post and author up by
// primary key, rather than filtering the whole Posts table. Ordered by postId
// (auto-increment, so newest-first) to ride the primary key instead of sorting.
$rows = DB::rows('
SELECT `l`.`postId`, `l`.`latitude`, `l`.`longitude`, `p`.`title`, `u`.`slug`, `u`.`title` AS `authorName`
    FROM `PostLocations` `l`
    JOIN `Posts` `p` ON `p`.`postId` = `l`.`postId`
    JOIN `Users` `u` ON `u`.`userId` = `p`.`userId`
    WHERE `u`.`banned` = ?
    ORDER BY `l`.`postId` DESC
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
