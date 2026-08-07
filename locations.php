<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the posts are the same public ones the feed serves, just chosen by
// proximity.
//
// The canonical nearby page is a PLACE's page (/locations/{placeId}), not a
// coordinate pair's: coordinates arriving in the query string (the
// geolocation button, a pasted link) are rounded to the nearest place in the
// gazetteer and redirected there, so every spot in and around a town shares
// the one page instead of minting duplicate feeds per GPS reading. Only a
// point too far from anywhere nameable (or a server whose place directory
// hasn't loaded) renders as raw coordinates.
$place_id = isset($_GET['place']) ? (int) $_GET['place'] : null;

if ($place_id !== null) {
    $place = Place::load($place_id);

    if ($place === null) {
        require __DIR__ . '/404.php';
        exit;
    }

    $page = new Page([
        'title' => $place -> label(),
        'description' => 'Posts near ' . $place -> label() . '.',
        'needsMath' => true,
    ]);

    $page -> addContent(new NearbyFeedSection([
        'latitude' => (float) $place -> latitude,
        'longitude' => (float) $place -> longitude,
        'heading' => 'Posts near ' . $place -> label(),
    ]));

    $page -> send();
    exit;
}

$origin = Coordinates::parse($_GET['lat'] ?? null, $_GET['lng'] ?? null);

if ($origin !== null) {
    $nearest = Place::nearest($origin -> latitude, $origin -> longitude);

    if ($nearest !== null) {
        header('Location: ' . ServerURL::absolute('/locations/' . $nearest -> placeId), true, 302);
        exit;
    }
}

$page = new Page([
    'title' => 'Locations',
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
