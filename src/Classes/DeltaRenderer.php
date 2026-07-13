<?php

declare(strict_types=1);

/**
 * Server-side mirror of render_delta() in delta.js: turns a Quill Delta (its
 * decoded ops array) into a .PostBody DOM subtree, for the initial page /
 * permalink / RSS render. The rendered HTML is byte-for-byte the shape the
 * client builds from the same ops, so a post looks identical whether it came
 * in the page or over AJAX. Formula embeds emit a .PostFormula span carrying
 * the LaTeX source (KaTeX is JS-only) for the client render_formulas() pass.
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

        foreach ($this -> ops as $op) {
            $insert = $op['insert'] ?? null;

            if (is_string($insert)) {
                $segments = explode("\n", $insert);
                $last = count($segments) - 1;

                foreach ($segments as $index => $text) {
                    if ($text !== '') {
                        $inline[] = self::inlineNode($doc, $text, $op['attributes'] ?? []);
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

    private static function inlineNode(\DOMDocument $doc, string $text, array $attrs): \DOMNode
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

        $link = $attrs['link'] ?? null;

        if (is_string($link) && self::isSafeLink($link)) {
            $anchor = $doc -> createElement('a');
            $anchor -> setAttribute('href', $link);
            $anchor -> setAttribute('target', '_blank');
            $anchor -> setAttribute('rel', 'noopener');
            $anchor -> appendChild($node);
            $node = $anchor;
        }

        return $node;
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
