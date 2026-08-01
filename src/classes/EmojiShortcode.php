<?php

declare(strict_types=1);

/**
 * Turns :shortcode: into the emoji it names.
 *
 * Only ever at the last step of output. Nothing anyone types is rewritten on
 * the way in - the stored post still says what its author wrote, and editing
 * gives it back unchanged. That also means a name this table does not know
 * stays exactly as typed, here and everywhere it is federated to.
 *
 * On the server this runs only for output that leaves the building: the
 * ActivityPub copy of a post and of a message. The page itself is left to
 * EmojiRenderer.js, which is already walking those text nodes to wrap emoji and
 * can substitute in the same pass.
 *
 * Which matters more than it sounds. A shortcode is a local convenience, not a
 * Fediverse format: ActivityPub carries emoji as literal characters, and
 * nothing on the receiving side expands :smile: for us. Unexpanded, it would
 * reach Mastodon as seven characters of text.
 *
 * The unrelated thing that shares this syntax is a custom emoji, which travels
 * as a per-post Emoji tag naming an image. Those are deliberately left alone
 * here: a name is only ever replaced when this table holds it, so a
 * :blobcat: passes through untouched for that tag to resolve later.
 */
class EmojiShortcode
{
    /**
     * A name is letters, digits, underscore, plus and hyphen - what the
     * generated table is restricted to. Anything else cannot be a shortcode, so
     * it is never even looked up.
     */
    private const PATTERN = '/:([a-z0-9_+-]+):/i';

    /**
     * Expands every shortcode in a rendered tree, except inside code.
     *
     * A walk rather than a substitution while the tree is being built: a code
     * block is marked on the line that ends it, not on the text inside it, so
     * at build time there is no way to know you are in one. Afterwards there
     * is - and it is the same rule EmojiRenderer.js applies on the client, so
     * the two agree about what is left alone.
     */
    public static function expandInDOM(\DOMElement $root, array $custom = []): void
    {
        $document = $root -> ownerDocument;

        if ($document === null) {
            return;
        }

        $xpath = new \DOMXPath($document);

        // Inline code and code blocks both, plus rendered formulae - a LaTeX
        // source carrying colons is not prose.
        $nodes = $xpath -> query('.//text()[not(ancestor::pre) and not(ancestor::code)]', $root);

        if ($nodes === false) {
            return;
        }

        // Collected before anything is replaced: swapping a node out while the
        // list is still live is asking for a surprise.
        $text_nodes = [];

        foreach ($nodes as $node) {
            $text_nodes[] = $node;
        }

        foreach ($text_nodes as $node) {
            $replacement = self::fragmentFor($document, (string) $node -> nodeValue, $custom);

            if ($replacement !== null) {
                $node -> parentNode ?-> replaceChild($replacement, $node);
            }
        }
    }

    /**
     * One text node's worth of expansion, or null when nothing in it changes.
     *
     * A custom emoji becomes an image and so needs real nodes, which is why
     * this returns a fragment rather than a string. A Unicode one is still just
     * text, and stays text.
     *
     * The custom map wins where both know a name. A tag is the sending server
     * stating what a shortcode means in THIS post, which is a more specific
     * claim than a table everyone shares.
     *
     * @param array<string, string> $custom
     */
    private static function fragmentFor(\DOMDocument $document, string $text, array $custom): ?\DOMDocumentFragment
    {
        if (!str_contains($text, ':')) {
            return null;
        }

        $matches = [];

        if (preg_match_all(self::PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        $fragment = $document -> createDocumentFragment();
        $cursor = 0;
        $changed = false;

        foreach ($matches[0] as $index => $whole) {
            $name = strtolower($matches[1][$index][0]);
            $image = $custom[$name] ?? null;
            $character = EmojiShortcodeMap::MAP[$name] ?? null;

            if ($image === null && $character === null) {
                continue;
            }

            $offset = (int) $whole[1];

            if ($offset > $cursor) {
                $fragment -> appendChild($document -> createTextNode(substr($text, $cursor, $offset - $cursor)));
            }

            if ($image !== null) {
                $element = $document -> createElement('img');
                $element -> setAttribute('class', 'CustomEmoji');
                $element -> setAttribute('src', $image);
                // The shortcode is the alt text: it is what the author wrote,
                // and the only description of the picture that exists.
                $element -> setAttribute('alt', ':' . $name . ':');
                $element -> setAttribute('title', ':' . $name . ':');
                $element -> setAttribute('loading', 'lazy');
                $fragment -> appendChild($element);
            } else {
                $fragment -> appendChild($document -> createTextNode((string) $character));
            }

            $cursor = $offset + strlen($whole[0]);
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        if ($cursor < strlen($text)) {
            $fragment -> appendChild($document -> createTextNode(substr($text, $cursor)));
        }

        return $fragment;
    }

    public static function expand(string $text): string
    {
        // Nothing that could match means nothing to do - worth checking, since
        // most text has no colons in it at all.
        if (!str_contains($text, ':')) {
            return $text;
        }

        return (string) preg_replace_callback(
            self::PATTERN,
            // Replaced only when the name is one we hold. Everything else is
            // left as it was typed, which is what keeps a clock time, a ratio
            // and a custom emoji from being mangled.
            static fn (array $match): string => EmojiShortcodeMap::MAP[strtolower($match[1])] ?? $match[0],
            $text
        );
    }
}
