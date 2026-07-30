<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$shard = $_GET['shard'] ?? null;

if ($shard !== null) {
    new OPMLShard(['shard' => (int) $shard]) -> send();
} else {
    new OPMLIndex() -> send();
}
