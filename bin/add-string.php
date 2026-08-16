<?php

declare(strict_types=1);

// Adds one interface string, in English only: `php bin/add-string.php
// PostCard.replyCount "{count} replies"`.
//
// The English goes into locales/en.json, which is what makes the key stale in
// every other locale - naming a key that already exists rewrites it, and
// every language falls stale from the new wording the same way. Nothing
// beyond English is written here: a locale's own words for the new key come
// from a direct, by-hand translation pass, not from this running one.

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

// Counted rather than tested for emptiness: "" is a thing to say here. It is
// how a piece of a split sentence says English has no words at that end of it,
// which every other language is then free to fill in - see MoreLocationsLink,
// where English writes "See {link}" and Japanese writes "{link}を見る".
if ($argc < 3 || $path === '') {
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

if (file_put_contents($source, $json . "\n") === false) {
    fwrite(STDERR, 'Could not write ' . $source . ".\n");
    exit(1);
}

echo ($existing === null ? 'Added ' : 'Rewrote ') . $path . ': ' . $text . "\n";
