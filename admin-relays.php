<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

// The primary admin only, not moderators: a relay subscription commits the
// server's storage and bandwidth, which is the person who runs it to decide.
if (Auth::id() !== 1) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => 'Relays']);

$page -> addContent(new RelaySubscribeForm());
$page -> addContent(new RelayList());

$page -> send();
