<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(static function (string $class): void {
    $path = __DIR__ . '/../src/classes/' . $class . '.php';
    if (is_file($path)) {
        require $path;
    }
});
require __DIR__ . '/../src/functions.php';

try {
    echo json_encode(ActorDiscovery::discover($argv[1] ?? '', $argv[2] ?? 'unknown', ($argv[3] ?? '') === '1'), JSON_THROW_ON_ERROR);
} catch (\Throwable $exception) {
    exit(1);
}
