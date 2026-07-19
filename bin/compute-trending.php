<?php

declare(strict_types=1);

// Recomputes TrendingEntities from the current window of posts - see
// Trending.php for the scoring/window/abuse-guard design. Intended to run
// every ~10-15 min from a systemd user timer, mirroring bin/backup.php - see
// README's "Trending" section. Trending::current() also self-heals via a
// lottery-triggered recompute if this timer isn't installed, so running it
// is an optimization (fresher data, no read-path latency spike on the
// unlucky request that draws the lottery), not a hard requirement.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Trending::recompute();

// The /tags/ Popular graph and Trending cloud are materialized the same way -
// recomputed here on the same timer rather than aggregated at read time.
HashtagGraph::recompute();
TrendingHashtagList::recompute();
