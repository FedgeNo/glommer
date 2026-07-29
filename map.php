<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the map is for everyone, the same view of public posts a logged-out
// visitor already gets from the feed.
$page = new Page([
    'title' => 'Map',
    'description' => 'A map of posts from around the world - find people and things near you.',
    'needsMap' => true,
]);

$page -> addContent(new PostMap());

$page -> send();
