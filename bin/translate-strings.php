<?php

declare(strict_types=1);

// Fills each locale's calendar block (month names, date/time patterns, the
// clock) from ICU: `php bin/translate-strings.php [--force] [locale ...]`.
// CLI-only, and safe to rerun - it only fills what is stale or missing, so a
// rerun with nothing new does nothing at all. --force refills everything,
// for when ICU's own data changes rather than the words.
//
// Prose is not translated here. A source string going stale - even from
// something as small as a decoration coming out of it - is not the same
// thing as a translation being wrong, and running everything stale back
// through a model treats the two as if they were: it can just as easily
// replace a translation that was already correct with one that reads worse.
// A locale's own words are written directly, by hand, one locale at a time.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Standalone helpers have no class name for the autoloader to find them by.
require __DIR__ . '/../src/functions.php';

$arguments = array_slice($argv, 1);
$force = in_array('--force', $arguments, true);
$only = array_values(array_filter($arguments, fn (string $argument): bool => !str_starts_with($argument, '--')));

$translator = new StringTranslator($only, $force);
$locales = $translator -> locales();

if ($locales === []) {
    fwrite(STDERR, "No languages to translate into - install the Argos packages first.\n");
    exit(1);
}

$started_at = microtime(true);

try {
    $translator -> run();
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Translation failed: ' . $exception -> getMessage() . "\n");
    exit(1);
}

echo count($locales) . ' locale(s) in ' . (int) round(microtime(true) - $started_at) . "s.\n";
