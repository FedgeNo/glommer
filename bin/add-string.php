<?php

declare(strict_types=1);

// Adds one interface string and translates it into every language the site has
// a locale file for: `php bin/add-string.php PostCard.replyCount "{count}
// replies"`.
//
// The English goes into locales/en.json, which is what makes the key stale in
// every other locale; only that key is then translated, so this costs a moment
// rather than the length of a whole pass. Naming a key that already exists
// rewrites it, and every language is retranslated from the new wording - which
// is the same thing editing en.json by hand would do, done in one step.

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

$path = (string) ($argv[1] ?? '');
$text = (string) ($argv[2] ?? '');

if ($path === '' || $text === '') {
    fwrite(STDERR, "Usage: php bin/add-string.php <Class.key> <English text>\n");
    exit(1);
}

// A key names a class and something within it, which is what the locale files
// are shaped like - a bare word at the top level would be a string no class
// could ask for.
if (preg_match('/^[A-Za-z][A-Za-z0-9]*(\.[A-Za-z0-9_-]+)+$/', $path) !== 1) {
    fwrite(STDERR, 'Not a usable key: ' . $path . ". Expected something like PostCard.replyCount.\n");
    exit(1);
}

$source = __DIR__ . '/../locales/' . Strings::SOURCE_LOCALE . '.json';
$table = json_decode((string) file_get_contents($source), true);

if (!is_array($table)) {
    fwrite(STDERR, $source . " is not readable as JSON.\n");
    exit(1);
}

$existing = StringTranslator::flatten($table)[$path] ?? null;

if ($existing === $text) {
    echo $path . " already says that.\n";
    exit;
}

// Walked rather than assigned, so a new key lands inside the class it names
// and a new class lands at the end - the file stays in the order it was
// written in either way.
$branch = &$table;

foreach (explode('.', $path) as $segment) {
    if (!isset($branch[$segment]) || !is_array($branch[$segment])) {
        $branch[$segment] = [];
    }

    $branch = &$branch[$segment];
}

$branch = $text;
unset($branch);

$json = json_encode($table, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    fwrite(STDERR, "Could not encode the source strings.\n");
    exit(1);
}

// Checked, because everything after this point translates that key into every
// language from an en.json this was supposed to have written: a refused write
// would leave the English behind and the translations of it everywhere.
if (file_put_contents($source, $json . "\n") === false) {
    fwrite(STDERR, 'Could not write ' . $source . ".\n");
    exit(1);
}

echo ($existing === null ? 'Added ' : 'Rewrote ') . $path . ': ' . $text . "\n";

$translator = new StringTranslator([], false, [$path]);
$locales = $translator -> locales();

if ($locales === []) {
    echo "No languages installed to translate it into.\n";
    exit;
}

$started_at = microtime(true);

// The English is already written by now, so a failure here is one language
// short rather than a lost string - said plainly, and the run reruns.
try {
    $translator -> run();
} catch (\Throwable $exception) {
    fwrite(STDERR, 'The English was saved; translating it failed: ' . $exception -> getMessage() . "\n");
    exit(1);
}

echo count($locales) . ' locale(s) in ' . (int) round(microtime(true) - $started_at) . "s.\n";
