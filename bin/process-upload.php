<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$batch_id = $argv[1] ?? '';

if (!preg_match('/^[a-f0-9]{32}$/', $batch_id)) {
    exit(1);
}

UploadBatch::process($batch_id);
