<?php

declare(strict_types=1);

/**
 * Server-side mirror of render_delta() in delta.js: turns a Quill Delta (its
 * decoded ops array) into a .PostBody DOM subtree, for the initial page /
 * permalink render. The rendered HTML is byte-for-byte the shape the client
 * builds from the same ops, so a post looks identical whether it came in the
 * page or over AJAX. Formula embeds emit a .PostFormula span carrying the LaTeX
 * source (KaTeX is JS-only) for the client render_formulas() pass.
 *
 * The render runs the "honest links" pass (see Linkify), kept identical to
 * delta.js: pass 1 strips the href off any link whose visible text reads as a
 * URL (anti-phishing), pass 2 linkifies bare URLs (self-links) and #hashtags in
 * plain text. External links open in a new tab; internal/hashtag links open in
 * place.
 */
class DeltaRenderer extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'PostBody';

    private const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    /** @param array[] $ops the Delta's ops */
    public function __construct(private readonly array $ops = [])
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $doc = self::currentDocument();

        $root = $doc -> createElement('div');
        $root -> setAttribute('class', 'PostBody');

        // Pass 1: neutralise deceptive anchors before rendering.
        $ops = self::stripDeceptiveLinks($this -> ops);

        /** @var \DOMNode[] $inline */
        $inline = [];
        $list_el = null;
        $list_kind = null;

        $flush = function (array $attrs) use (&$inline, &$list_el, &$list_kind, $doc, $root): void {
            $list = $attrs['list'] ?? null;

            if ($list === 'ordered' || $list === 'bullet') {
                if ($list_el === null || $list_kind !== $list) {
                    $list_el = $doc -> createElement($list === 'ordered' ? 'ol' : 'ul');
                    $list_kind = $list;
                    $root -> appendChild($list_el);
                }

                $li = $doc -> createElement('li');

                foreach ($inline as $node) {
                    $li -> appendChild($node);
                }

                $list_el -> appendChild($li);
                $inline = [];

                return;
            }

            $list_el = null;
            $list_kind = null;

            $header = $attrs['header'] ?? null;

            if ($header === 1 || $header === 2 || $header === 3) {
                $block = $doc -> createElement('h' . $header);
            } elseif ($attrs['blockquote'] ?? false) {
                $block = $doc -> createElement('blockquote');
            } elseif ($attrs['code-block'] ?? false) {
                $block = $doc -> createElement('pre');
            } else {
                $block = $doc -> createElement('p');
            }

            foreach ($inline as $node) {
                $block -> appendChild($node);
            }

            if ($inline === [] && $block -> tagName === 'p') {
                $block -> appendChild($doc -> createElement('br'));
            }

            $root -> appendChild($block);
            $inline = [];
        };

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;

            if (is_string($insert)) {
                $segments = explode("\n", $insert);
                $last = count($segments) - 1;

                foreach ($segments as $index => $text) {
                    if ($text !== '') {
                        foreach (self::inlineNodes($doc, $text, $op['attributes'] ?? []) as $node) {
                            $inline[] = $node;
                        }
                    }

                    if ($index < $last) {
                        $flush($op['attributes'] ?? []);
                    }
                }
            } elseif (is_array($insert) && isset($insert['formula']) && is_string($insert['formula'])) {
                $inline[] = self::formulaNode($doc, $insert['formula']);
            }
        }

        if ($inline !== []) {
            $flush([]);
        }

        return $root;
    }

    /**
     * Pass 1: group consecutive string ops sharing a link value and, if the
     * group's combined text reads as a URL, strip the link from all of them - so
     * a URL shown as link text can't hide a different href (the split across
     * formatting ops is exactly why grouping, not per-op, is required). Non-link
     * and non-string ops pass through and break a group.
     *
     * @param array[] $ops
     * @return array[]
     */
    private static function stripDeceptiveLinks(array $ops): array
    {
        $result = [];
        $group = [];
        $group_text = '';
        $group_link = null;

        $resolve = function () use (&$result, &$group, &$group_text, &$group_link): void {
            if ($group !== [] && Linkify::textLooksURL($group_text)) {
                foreach ($group as $i) {
                    unset($result[$i]['attributes']['link']);

                    if (($result[$i]['attributes'] ?? []) === []) {
                        unset($result[$i]['attributes']);
                    }
                }
            }

            $group = [];
            $group_text = '';
            $group_link = null;
        };

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;
            $link = is_string($insert) ? ($op['attributes']['link'] ?? null) : null;

            if (is_string($link)) {
                if ($group_link !== null && $link !== $group_link) {
                    $resolve();
                }

                $result[] = $op;
                $group[] = count($result) - 1;
                $group_text .= $insert;
                $group_link = $link;
            } else {
                $resolve();
                $result[] = $op;
            }
        }

        $resolve();

        return $result;
    }

    /**
     * The inline nodes for a standalone plain-text string (a user bio): the
     * same pass-2 URL/hashtag/mention linkifying a post gets, with no Delta
     * block or inline-format attributes. Lets a non-Delta text field share the
     * one linkifier (and its PHP/JS parity via Linkify) rather than growing its
     * own. Newlines stay as text - the caller preserves them with CSS.
     *
     * @return \DOMNode[]
     */
    public static function linkifyPlainText(\DOMDocument $doc, string $text): array
    {
        return self::inlineNodes($doc, $text, []);
    }

    /**
     * Pass 2: the inline node(s) for one text run. A run with a link that
     * survived pass 1 is a URL-free label -> one honest anchor. Inline code is
     * never linkified. Otherwise the text is tokenized: URLs become self-links,
     * #hashtags become tag links, the rest stays plain - each segment wrapped in
     * the run's formatting, anchor outermost.
     *
     * @return \DOMNode[]
     */
    private static function inlineNodes(\DOMDocument $doc, string $text, array $attrs): array
    {
        $link = $attrs['link'] ?? null;

        if (is_string($link)) {
            return [self::linkedNode($doc, $link, self::formattedTextNode($doc, $text, $attrs))];
        }

        if ($attrs['code'] ?? false) {
            return [self::formattedTextNode($doc, $text, $attrs)];
        }

        $nodes = [];

        foreach (Linkify::tokenize($text) as $segment) {
            $inner = self::formattedTextNode($doc, $segment['text'], $attrs);

            if ($segment['type'] === 'url') {
                $nodes[] = self::linkedNode($doc, $segment['text'], $inner);
            } elseif ($segment['type'] === 'hashtag') {
                $nodes[] = self::hashtagNode($doc, $segment['tag'], $inner);
            } elseif ($segment['type'] === 'mention') {
                $nodes[] = self::mentionNode($doc, $segment['username'], $inner);
            } else {
                $nodes[] = $inner;
            }
        }

        return $nodes;
    }

    /** A text node wrapped in the run's inline formatting (no link). */
    private static function formattedTextNode(\DOMDocument $doc, string $text, array $attrs): \DOMNode
    {
        $node = $doc -> createTextNode($text);

        if ($attrs['code'] ?? false) {
            $node = self::wrap($doc, 'code', $node);
        }

        if ($attrs['bold'] ?? false) {
            $node = self::wrap($doc, 'strong', $node);
        }

        if ($attrs['italic'] ?? false) {
            $node = self::wrap($doc, 'em', $node);
        }

        if ($attrs['underline'] ?? false) {
            $node = self::wrap($doc, 'u', $node);
        }

        if ($attrs['strike'] ?? false) {
            $node = self::wrap($doc, 's', $node);
        }

        return $node;
    }

    /** An anchor to $href (external -> new tab), or the bare node if unsafe. */
    private static function linkedNode(\DOMDocument $doc, string $href, \DOMNode $inner): \DOMNode
    {
        if (!self::isSafeLink($href)) {
            return $inner;
        }

        $anchor = $doc -> createElement('a');
        $anchor -> setAttribute('href', $href);

        if (self::opensInNewTab($href)) {
            $anchor -> setAttribute('target', '_blank');
            $anchor -> setAttribute('rel', 'noopener');
        }

        $anchor -> appendChild($inner);

        return $anchor;
    }

    /** An internal (same-window) anchor to a hashtag's tag page. */
    private static function hashtagNode(\DOMDocument $doc, string $tag, \DOMNode $inner): \DOMElement
    {
        $anchor = $doc -> createElement('a');
        $anchor -> setAttribute('href', ServerURL::absolute('/tags/' . $tag));
        $anchor -> appendChild($inner);

        return $anchor;
    }

    /** An internal (same-window) anchor to a mentioned user's profile. */
    private static function mentionNode(\DOMDocument $doc, string $username, \DOMNode $inner): \DOMElement
    {
        $anchor = $doc -> createElement('a');
        $anchor -> setAttribute('href', ServerURL::absolute('/users/' . $username . '/'));
        $anchor -> appendChild($inner);

        return $anchor;
    }

    private static function opensInNewTab(string $href): bool
    {
        $host = Linkify::linkHost($href);

        if ($host === null) {
            return false;
        }

        return $host !== ServerURL::host();
    }

    private static function wrap(\DOMDocument $doc, string $tag, \DOMNode $child): \DOMElement
    {
        $element = $doc -> createElement($tag);
        $element -> appendChild($child);

        return $element;
    }

    private static function formulaNode(\DOMDocument $doc, string $source): \DOMElement
    {
        $span = $doc -> createElement('span');
        $span -> setAttribute('class', 'PostFormula');
        $span -> setAttribute('data-formula', $source);
        $span -> appendChild($doc -> createTextNode($source));

        return $span;
    }

    private static function isSafeLink(string $url): bool
    {
        // Browsers strip ASCII whitespace and control chars while parsing a
        // URL, so "java\tscript:alert(1)" runs as javascript: even though the
        // raw scheme test wouldn't match it. Strip those first (interior ones
        // too, not just the ends trim() would catch) before reading the scheme.
        $stripped = preg_replace('/[\x00-\x20]+/', '', $url);

        if (!preg_match('/^([a-z][a-z0-9+.\-]*):/i', $stripped, $match)) {
            return true;
        }

        return in_array(strtolower($match[1]), self::ALLOWED_LINK_SCHEMES, true);
    }
}
