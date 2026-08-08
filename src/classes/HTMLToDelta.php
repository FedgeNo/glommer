<?php

declare(strict_types=1);

/**
 * HTML read into the delta this site stores.
 *
 * Everything written here is a delta - the composer produces one, both
 * renderers build from one - so anything arriving as markup has to become one
 * before it can be a post. Inbound Fediverse content is the first caller: a
 * post from elsewhere arrives as HTML and was flattened to plain text, which
 * cost it every link, every emphasis and every list it was written with.
 *
 * A delta is flat: inline runs carry their own formatting, and a block's kind
 * lives on the newline that ends it. Nesting therefore cannot survive - a list
 * inside a quote is a quoted list here, not a list within a quote - so this
 * flattens deliberately rather than by accident, an inner block winning the
 * attribute it shares with an outer one.
 *
 * Nothing it produces is taken on trust: the ops go through Delta::sanitize()
 * on the way out, which is the same gate a locally-typed post passes and the
 * only thing that decides which attributes may exist at all.
 */
class HTMLToDelta
{
    /** Tags that change how a run reads, and the attribute each one sets. */
    private const INLINE_TAGS = [
        'strong' => 'bold',
        'b' => 'bold',
        'em' => 'italic',
        'i' => 'italic',
        'u' => 'underline',
        's' => 'strike',
        'del' => 'strike',
        'strike' => 'strike',
        'code' => 'code',
    ];

    /** Tags that hold a block's worth of content without naming a kind. */
    private const PLAIN_BLOCK_TAGS = ['p', 'div', 'section', 'article', 'header', 'footer', 'tr', 'dd', 'dt'];

    /** Tags whose content is not writing and must not become any. */
    private const SKIPPED_TAGS = ['script', 'style', 'head', 'template', 'noscript'];

    /** @var array[] the finished ops */
    private array $ops = [];

    /** @var array[] the inline runs of the block still being read */
    private array $pending = [];

    /**
     * @return array[] delta ops, sanitized - empty when the HTML held no words
     */
    public static function convert(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        // A real HTML5 parser rather than a pattern: what arrives is somebody
        // else's markup, and the shapes it can take are the browser's problem
        // to define, not this file's.
        $document = \Dom\HTMLDocument::createFromString(
            '<!DOCTYPE html><body>' . $html,
            LIBXML_NOERROR
        );

        $converter = new self();
        $converter -> walk($document -> body, [], []);
        $converter -> endBlock([]);

        return Delta::sanitize($converter -> ops);
    }

    private function walk(\Dom\Node $node, array $inline, array $block): void
    {
        foreach (iterator_to_array($node -> childNodes) as $child) {
            if ($child instanceof \Dom\Text) {
                $this -> addText($child -> data, $inline, $block);

                continue;
            }

            if (!($child instanceof \Dom\Element)) {
                continue;
            }

            $tag = strtolower($child -> localName);

            if (in_array($tag, self::SKIPPED_TAGS, true)) {
                continue;
            }

            if ($tag === 'br') {
                $this -> endBlock($block);

                continue;
            }

            // The shape DeltaRenderer gives a formula, read back as the embed
            // it came from. Its text is the same LaTeX, so recursing would
            // file the source twice, once as words.
            $formula = (string) $child -> getAttribute('data-formula');

            if ($formula !== '') {
                $this -> pending[] = ['insert' => ['formula' => $formula]];

                continue;
            }

            if (isset(self::INLINE_TAGS[$tag])) {
                // <pre><code> is one construct - a fenced block - rather than
                // a code span that happens to sit inside a code block, so the
                // inner tag adds nothing the outer one has not already said.
                $redundant = $tag === 'code' && isset($block['code-block']);

                $this -> walk($child, $redundant ? $inline : array_merge($inline, [self::INLINE_TAGS[$tag] => true]), $block);

                continue;
            }

            if ($tag === 'a') {
                $href = trim((string) $child -> getAttribute('href'));

                // A hashtag arrives linked to the tag page of the server that
                // wrote the post, which is not where a reader here means to
                // go: #cats is a subject, and the subject on this site is this
                // site's tag page. Carrying no link, the words come out as
                // plain text and the renderer's own tokenizer makes the link -
                // the same one it makes for a tag typed here.
                //
                // Only tags. A mention travels as "@user" without its domain,
                // so relinking one locally would point at whoever here happens
                // to share the name, or at nobody.
                $hashtag = str_starts_with(trim((string) $child -> textContent), '#');

                $carried = ($href === '' || $hashtag) ? $inline : array_merge($inline, ['link' => $href]);

                $this -> walk($child, $carried, $block);

                continue;
            }

            $block_attributes = self::blockAttributes($tag, $child, $block);

            // Something with no block of its own - a span, a font - whose
            // contents belong to whatever block already encloses them.
            if ($block_attributes === null) {
                $this -> walk($child, $inline, $block);

                continue;
            }

            $this -> walk($child, $inline, $block_attributes);
            $this -> endBlock($block_attributes);
        }
    }

    /**
     * The block a tag opens, merged over the one enclosing it - or null when
     * the tag opens no block at all.
     */
    private static function blockAttributes(string $tag, \Dom\Element $element, array $enclosing): ?array
    {
        return match (true) {
            $tag === 'h1' => array_merge($enclosing, ['header' => 1]),
            $tag === 'h2' => array_merge($enclosing, ['header' => 2]),
            // Three is the smallest heading this site renders, so everything
            // below it arrives as one rather than as nothing.
            in_array($tag, ['h3', 'h4', 'h5', 'h6'], true) => array_merge($enclosing, ['header' => 3]),
            $tag === 'blockquote' => array_merge($enclosing, ['blockquote' => true]),
            $tag === 'pre' => array_merge($enclosing, ['code-block' => true]),
            $tag === 'li' => array_merge($enclosing, ['list' => self::listKind($element)]),
            in_array($tag, self::PLAIN_BLOCK_TAGS, true) => $enclosing,
            default => null,
        };
    }

    /** Numbered only inside an <ol>; everything else is a bullet. */
    private static function listKind(\Dom\Element $item): string
    {
        for ($parent = $item -> parentNode; $parent instanceof \Dom\Element; $parent = $parent -> parentNode) {
            $tag = strtolower($parent -> localName);

            if ($tag === 'ol') {
                return 'ordered';
            }

            if ($tag === 'ul') {
                return 'bullet';
            }
        }

        return 'bullet';
    }

    private function addText(string $text, array $inline, array $block): void
    {
        // Inside a <pre> the spacing is the content, and a newline there ends
        // a line of code rather than joining one.
        if (isset($block['code-block'])) {
            $lines = explode(chr(10), str_replace(chr(13), '', $text));

            foreach ($lines as $index => $line) {
                if ($index > 0) {
                    $this -> endBlock($block);
                }

                $this -> push($line, $inline);
            }

            return;
        }

        $this -> push(self::collapseWhitespace($text), $inline);
    }

    private function push(string $text, array $inline): void
    {
        if ($text === '') {
            return;
        }

        $this -> pending[] = $inline === []
            ? ['insert' => $text]
            : ['insert' => $text, 'attributes' => $inline];
    }

    /**
     * Ends the block being read, if anything is in it. An empty one is the
     * markup's own layout - a wrapper around a wrapper - rather than a blank
     * line somebody wrote.
     */
    private function endBlock(array $block): void
    {
        if ($this -> pending === []) {
            return;
        }

        foreach ($this -> pending as $op) {
            $this -> ops[] = $op;
        }

        $this -> pending = [];
        $this -> ops[] = $block === [] ? ['insert' => chr(10)] : ['insert' => chr(10), 'attributes' => $block];
    }

    /**
     * The whitespace rule the markup was written under: a run of it is one
     * space, and where it falls between two elements it still separates them.
     */
    private static function collapseWhitespace(string $text): string
    {
        $collapsed = '';
        $pending_space = false;

        for ($index = 0; $index < strlen($text); $index++) {
            $character = $text[$index];

            if ($character === ' ' || $character === chr(9) || $character === chr(10) || $character === chr(13)) {
                $pending_space = true;

                continue;
            }

            if ($pending_space) {
                $collapsed .= ' ';
                $pending_space = false;
            }

            $collapsed .= $character;
        }

        return $pending_space ? $collapsed . ' ' : $collapsed;
    }
}
