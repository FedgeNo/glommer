<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

$page = new GamePage(['title' => 'Texas Hold’em', 'game' => 'poker']);
$page -> addContent(new PokerGame(['signedIn' => Auth::check()]));
$page -> send();
