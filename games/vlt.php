<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

$page = new GamePage(['title' => 'Nova Vault', 'game' => 'vlt']);
$page -> addContent(new VLTGame());
$page -> send();
