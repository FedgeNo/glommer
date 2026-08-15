<?php

declare(strict_types=1);

// Translates the interface into every language the site has a locale file for:
// `php bin/translate-strings.php [--force] [locale ...]`. CLI-only, and safe to
// rerun - it translates only the strings whose English has changed since the
// last run, so a rerun with nothing edited does nothing at all. --force
// retranslates everything, for when the models change rather than the words.
//
// Needs the Argos packages, which are a production-only install (see README) -
// on a machine without them every locale reports that it has no package.

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
