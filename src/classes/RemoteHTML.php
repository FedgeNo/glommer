<?php

declare(strict_types=1);

/**
 * Reducing HTML that arrived from another server to plain text.
 *
 * Remote content is never trusted as markup - it is flattened to text and
 * escaped again on the way out, so a decoded "<" is inert rather than an
 * element. Block boundaries become newlines FIRST, or stripping the tags
 * would run every paragraph into one unreadable line; entities are decoded
 * after the tags are gone, so "&amp;" reads as "&" instead of its escape.
 *
 * The one implementation for everything inbound. A post and a direct message
 * used to flatten separately, with different ideas of which tags end a line -
 * so the same list arrived shaped differently depending on which kind of
 * object carried it.
 */
class RemoteHTML
{
    public static function toPlainText(string $html): string
    {
        $with_breaks = preg_replace('#<br\s*/?>|</(?:p|div|li|h[1-6]|blockquote|pre|tr)\s*>#i', "\n", $html);
        $plain = html_entity_decode(strip_tags((string) $with_breaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Invalid UTF-8 from a remote server would be rejected by the utf8mb4
        // columns and break json_encode; drop the bad bytes rather than 500.
        $plain = (string) mb_convert_encoding($plain, 'UTF-8', 'UTF-8');

        // Collapse the runs of blank lines the block-to-newline pass leaves
        // behind (nested block tags each contribute one), then trim.
        return trim((string) preg_replace('/\n{3,}/', "\n\n", $plain));
    }
}
