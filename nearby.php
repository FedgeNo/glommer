<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the posts are the same public ones the feed serves, just chosen by
// proximity. The origin travels in the query string, so a nearby feed is
// shareable and you can read another place's local feed by changing the URL.
$origin = Coordinates::parse($_GET['lat'] ?? null, $_GET['lng'] ?? null);

$page = new Page([
    'title' => 'Nearby',
    'description' => 'Posts from the places closest to you.',
    'needsMath' => true,
]);

if ($origin === null) {
    $page -> addContent(new NearbyLocationPrompt());
} else {
    $page -> addContent(new NearbyFeedSection([
        'latitude' => $origin -> latitude,
        'longitude' => $origin -> longitude,
    ]));
}

$page -> send();
