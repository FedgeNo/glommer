<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

// CLI runs default to an unlimited memory_limit; pin a ceiling so PHP's own
// in-process allocations - chiefly GD decoding/resizing an image in
// ImageProcessor - can't exhaust the host's RAM (belt-and-braces even though
// ImageProcessor already caps source pixels). This does NOT bound the ffmpeg/
// ffprobe transcodes: those run as separate OS processes via exec(), which a
// PHP memory_limit has no effect on - cap those at the OS level (ulimit, or a
// systemd slice with MemoryMax) if the host needs it.
ini_set('memory_limit', '512M');

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$batch_id = $argv[1] ?? '';

if (!preg_match('/^[a-f0-9]{32}$/', $batch_id)) {
    exit(1);
}

UploadBatch::process($batch_id);
