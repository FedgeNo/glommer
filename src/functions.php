<?php

declare(strict_types=1);

/**
 * Standalone helpers shared across the whole codebase - web requests, the
 * daemons, and the tests alike. Loaded explicitly by every bootstrap
 * (src/init.php, bin/run-tests.php, and each bin/ script that registers its
 * own autoloader), because functions have no class name for an autoloader to
 * find them by.
 *
 * Look here before writing a helper, and move one here on sight when it turns
 * up buried in a class it doesn't belong to.
 */

function truncate(string $str, int $len = 50): string
{
    if (mb_strlen($str) <= $len) {
        return $str;
    }

    $cut = mb_substr($str, 0, $len);
    $last_space = mb_strrpos($cut, ' ');

    // Back up to the last word boundary so the limit doesn't slice a word in
    // half - unless there's no space to back up to (a single long word), where
    // the hard cut stands.
    if ($last_space !== false) {
        $cut = mb_substr($cut, 0, $last_space);
    }

    return rtrim($cut) . '…';
}

/**
 * JSON safe to embed as the text of a <script> element.
 *
 * DOMDocument HTML-escapes text node content (&, <, >) regardless of the
 * parent tag. Browsers don't decode entities inside <script> (it's a "raw
 * text" element), so that escaping would corrupt the JSON. Encoding these
 * characters as JSON \uXXXX escapes first keeps them out of DOMDocument's
 * escaping pass while still round-tripping correctly through JSON.parse().
 */
function safe_json_for_script(mixed $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);

    return str_replace(['&', '<', '>'], ['\\u0026', '\\u003C', '\\u003E'], $json);
}

/** The canonical absolute URL of the request being served. */
function current_url(): string
{
    return ServerURL::absolute($_SERVER['REQUEST_URI'] ?? '/');
}

/**
 * A topic's name as an address segment: lowercased, with every run of anything
 * that is not a letter or a number becoming one hyphen.
 *
 * Letters and numbers of every script, not just ASCII - a topic is named in
 * whatever language somebody wrote the post in, and transliterating Ελλάδα to
 * "ellada" would make an address its own readers do not recognise.
 *
 * Two names that differ only in punctuation land on the same slug, which is the
 * intended reading: they are the same topic written two ways.
 */
function topic_slug(string $value): string
{
    $slug = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower(trim($value)));

    return trim($slug, '-');
}
