<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

$page = new GamePage(['title' => 'European roulette', 'game' => 'roulette']);
$page -> addContent(new RouletteGame());
$page -> send();
