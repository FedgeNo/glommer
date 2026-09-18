<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

$page = new GamePage(['title' => 'Neon Pachinko', 'game' => 'pachinko']);
$page -> addContent(new PachinkoGame());
$page -> send();
