<?php

declare(strict_types=1);

/**
 * Best-effort converter from the sanitized HTML that posts were stored as
 * before the Delta migration into Quill Delta ops. Used once to backfill
 * existing posts' descriptionDelta (and by UploadBatch for any batch staged
 * with the old HTML metadata before the deploy). It's the inverse of
 * DeltaRenderer over the exact tag vocabulary the old PostBody whitelist
 * allowed (p, br, h1-3, blockquote, pre, ul/ol/li, and inline strong/em/u/s/
 * code/a/span). Typed math delimiters were plain text then and stay plain text
 * here - only the new editor's formula button produces formula embeds.
 *
 * Callers should run the result through Delta::sanitize() (as the migration and
 * batch paths do) to normalise and validate it the same way a fresh post is.
 */
class HTMLToDelta
{
    /**
     * @return array[] the Delta ops
     */
    public static function convert(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The XML encoding hint makes DOMDocument read the markup as UTF-8; the
        // wrapper gives every block a single known parent to walk.
        $doc -> loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc -> getElementById('root');

        if ($root === null) {
            return [];
        }

        $ops = [];
        self::walkBlocks($root, $ops);

        return $ops;
    }

    private static function walkBlocks(\DOMNode $container, array &$ops): void
    {
        foreach ($container -> childNodes as $node) {
            if ($node instanceof \DOMText) {
                if (trim($node -> textContent) !== '') {
                    self::pushText($ops, $node -> textContent, []);
                    $ops[] = ['insert' => "\n"];
                }

                continue;
            }

            if (!($node instanceof \DOMElement)) {
                continue;
            }

            switch (strtolower($node -> tagName)) {
                case 'p':
                    self::emitBlock($node, $ops, []);
                    break;
                case 'h1':
                case 'h2':
                case 'h3':
                    self::emitBlock($node, $ops, ['header' => (int) substr($node -> tagName, 1)]);
                    break;
                case 'blockquote':
                    self::emitBlock($node, $ops, ['blockquote' => true]);
                    break;
                case 'pre':
                    self::emitBlock($node, $ops, ['code-block' => true]);
                    break;
                case 'ul':
                    self::emitList($node, $ops, 'bullet');
                    break;
                case 'ol':
                    self::emitList($node, $ops, 'ordered');
                    break;
                default:
                    // An unrecognised wrapper contributes only its contents.
                    self::walkBlocks($node, $ops);
            }
        }
    }

    /**
     * @param array<string, mixed> $block_attrs
     */
    private static function emitBlock(\DOMElement $element, array &$ops, array $block_attrs): void
    {
        self::walkInline($element, $ops, [], $block_attrs);
        $ops[] = self::lineOp($block_attrs);
    }

    private static function emitList(\DOMElement $list, array &$ops, string $kind): void
    {
        foreach ($list -> childNodes as $item) {
            if ($item instanceof \DOMElement && strtolower($item -> tagName) === 'li') {
                self::walkInline($item, $ops, [], ['list' => $kind]);
                $ops[] = self::lineOp(['list' => $kind]);
            }
        }
    }

    /**
     * @param array<string, mixed> $inline_attrs  formats inherited from ancestors
     * @param array<string, mixed> $block_attrs   this line's block type, for <br> breaks
     */
    private static function walkInline(\DOMNode $node, array &$ops, array $inline_attrs, array $block_attrs): void
    {
        foreach ($node -> childNodes as $child) {
            if ($child instanceof \DOMText) {
                self::pushText($ops, $child -> textContent, $inline_attrs);

                continue;
            }

            if (!($child instanceof \DOMElement)) {
                continue;
            }

            $tag = strtolower($child -> tagName);

            if ($tag === 'br') {
                $ops[] = self::lineOp($block_attrs);

                continue;
            }

            $attrs = $inline_attrs;

            switch ($tag) {
                case 'strong':
                case 'b':
                    $attrs['bold'] = true;
                    break;
                case 'em':
                case 'i':
                    $attrs['italic'] = true;
                    break;
                case 'u':
                    $attrs['underline'] = true;
                    break;
                case 's':
                case 'strike':
                case 'del':
                    $attrs['strike'] = true;
                    break;
                case 'code':
                    $attrs['code'] = true;
                    break;
                case 'a':
                    $href = $child -> getAttribute('href');

                    if ($href !== '') {
                        $attrs['link'] = $href;
                    }

                    break;
                // span (and anything else inline) just passes its formats through.
            }

            self::walkInline($child, $ops, $attrs, $block_attrs);
        }
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private static function pushText(array &$ops, string $text, array $attrs): void
    {
        // A stored text node's own newlines were rendered whitespace, never
        // line breaks (those were <br>/<p>); a raw "\n" in a Delta insert would
        // wrongly become a block break, so flatten them to spaces.
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);

        if ($text === '') {
            return;
        }

        $op = ['insert' => $text];

        if ($attrs !== []) {
            $op['attributes'] = $attrs;
        }

        $ops[] = $op;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array{insert: string, attributes?: array}
     */
    private static function lineOp(array $attrs): array
    {
        $op = ['insert' => "\n"];

        if ($attrs !== []) {
            $op['attributes'] = $attrs;
        }

        return $op;
    }
}
