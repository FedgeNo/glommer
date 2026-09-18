<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

$page = new GamePage(['title' => 'Singularity', 'game' => 'singularity']);
$page -> addContent(new SingularityGame());
$page -> send();
