<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$current_user = Auth::user();

// Linking to a point opens the map there - a post's place line does exactly
// this, so "where was that posted from" is one click and lands on the map
// rather than in a list.
$centre = Coordinates::parse($_GET['lat'] ?? null, $_GET['lng'] ?? null);

// Public - the map is for everyone, the same view of public posts a logged-out
// visitor already gets from the feed. The editor only loads for someone who can
// actually post to a spot they click.
$page = new Page([
    'title' => 'Map',
    'description' => 'A map of posts from around the world - find people and things near you.',
    'needsMap' => true,
    'needsEditor' => $current_user !== null,
    'needsEmoji' => $current_user !== null,
]);

$page -> addContent(new PostMap([
    'latitude' => $centre ?-> latitude,
    'longitude' => $centre ?-> longitude,
]));

if ($current_user !== null) {
    $page -> addContent(new MapComposer());
}

$page -> send();
