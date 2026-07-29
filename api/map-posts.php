<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

// Public, read-only: the map shows everyone's geotagged posts. This is a fixed,
// capped listing (not a full-text search), so it isn't the open-search DDoS
// hazard the search endpoints guard against - it's bounded and cache-friendly.
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
