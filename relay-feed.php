<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Members only: this is other servers' writing, arriving because an admin
// subscribed to a relay, not content this site puts in front of the world.
Auth::requireLogin();

$page = new Page([
    'title' => 'Relay Feed',
    'description' => 'Public posts arriving from the relays this server subscribes to.',
    'needsMath' => true,
]);

$page -> addContent(new RelayFeedList());

$page -> send();
