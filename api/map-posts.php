<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

// Public, read-only: the map shows everyone's geotagged posts. The listing is
// fixed and capped rather than a user-controlled search, but it needs no login,
// so it's paced per client to keep it from being used as a cheap
// database-load lever or as a way to sweep the site's locations.
$rate_key = 'map-posts:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 20, 60)) {
    JSONResponse::error('Too many requests. Please slow down.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

// A member sees where a post was actually made; anyone else sees the
// neighbourhood it was made in. Posts are public and so are their places, but
// exact coordinates handed to an anonymous caller in bulk is a different thing
// from a pin on a map - it is everybody's address, harvestable in a few
// requests. Two decimal places is a bit over a kilometre: enough to place a
// post in a town, not enough to place it at a door.
$exact_locations = Auth::check();
$rounding = 2;
// Driven from PostLocations: it holds only the posts that actually have a
// location, so this reads a small table and looks each post and author up by
// primary key, rather than filtering the whole Posts table. Ordered by postId
// (auto-increment, so newest-first) to ride the primary key instead of sorting.
$rows = DB::rows('
SELECT `l`.`postId`, `l`.`latitude`, `l`.`longitude`, `p`.`title`, `p`.`createdAt`, `u`.`slug`, `u`.`title` AS `authorName`
    FROM `PostLocations` `l`
    JOIN `Posts` `p` ON `p`.`postId` = `l`.`postId`
    JOIN `Users` `u` ON `u`.`userId` = `p`.`userId`
    WHERE `u`.`banned` = ?
    ORDER BY `l`.`postId` DESC
    LIMIT 1000
', \stdClass::class, 'i', 0);

$posts = [];

foreach ($rows as $row) {
    $posts[] = [
        'postId' => (int) $row -> postId,
        'latitude' => $exact_locations ? (float) $row -> latitude : round((float) $row -> latitude, $rounding),
        'longitude' => $exact_locations ? (float) $row -> longitude : round((float) $row -> longitude, $rounding),
        'title' => $row -> title,
        // Drives the time scrubber, which replays the map from the first
        // located post to now.
        'createdAt' => $row -> createdAt,
        'authorName' => $row -> authorName !== null && $row -> authorName !== '' ? $row -> authorName : $row -> slug,
        'url' => ServerURL::absolute('/users/' . $row -> slug . '/' . (int) $row -> postId),
    ];
}

JSONResponse::success(['posts' => $posts]) -> send();
