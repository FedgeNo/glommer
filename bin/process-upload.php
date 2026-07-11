<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

// CLI runs default to an unlimited memory_limit; pin a ceiling so a large image
// or video decode can't exhaust the host's RAM. Bounds the worst case even
// though ImageProcessor already caps source pixels.
ini_set('memory_limit', '512M');

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
