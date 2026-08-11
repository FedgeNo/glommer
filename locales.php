<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

/**
 * The string table as an ES module, for the client twins.
 *
 * Served rather than kept as a second file, because the two renderers already
 * work hard not to drift and a hand-maintained copy of every string would be
 * the easiest drift there is. Generated from the same src/locales/ array the
 * server reads - the manifest is served this way for the same reason.
 *
 * Public: it is the text on the buttons, which anybody looking at the site can
 * already read.
 */
$requested = strtolower(trim((string) ($_GET['locale'] ?? '')));

// Against the directory listing, not a pattern: the answer to "which locale"
// has to be one that exists, and Strings::all() would otherwise be asked for a
// name that came off the URL.
if (!in_array($requested, Strings::available(), true)) {
    http_response_code(404);
    exit;
}

$table = json_encode(Strings::all($requested), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if ($table === false) {
    http_response_code(500);
    exit;
}

// Revalidated rather than held: a translation is corrected by editing a file,
// and a reader holding a week-old copy of the words would have no way to know.
$etag = '"' . md5($table) . '"';

header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: no-cache');
header('ETag: ' . $etag);

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

echo 'export const STRINGS = ', $table, ";\n";
