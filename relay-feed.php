<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Members only: this is other servers' writing, arriving because an admin
// subscribed to a relay, not content this site puts in front of the world.
Auth::requireLogin();

$page = new Page([
    'title' => (string) (Strings::for('PageTitle')['relayFeed'] ?? ''),
    'description' => (string) (Strings::for('PageTitle')['relayFeedDescription'] ?? ''),
    'needsMath' => true,
]);

$page -> addContent(new RelayFeedList());

$page -> send();
