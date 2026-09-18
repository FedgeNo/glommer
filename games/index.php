<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

$page = new GamePage(['title' => 'Games', 'game' => 'index']);
$page -> addContent(new GameRoom());
$page -> send();
