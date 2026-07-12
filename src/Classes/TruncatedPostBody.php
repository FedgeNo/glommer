<?php

declare(strict_types=1);

/**
 * A PostBody that also truncates its rendered content to a visible-character
 * budget, for the short rich previews shown on feed posts. It extends
 * PostBody so it inherits the exact same sanitizing whitelist - truncation
 * is a second pass over the already-cleaned DOM, never a substitute for
 * cleaning (parent::toDOM() runs the whitelist first).
 *
 * Because it works on the DOM tree rather than the HTML string, a cut can
 * never leave a tag unclosed: it removes whole nodes and trims text nodes,
 * and serialization always closes whatever survives. Two things are treated
 * as atomic - never split through:
 *   - a LaTeX/KaTeX span (the app's $$...$$, \[...\] and \(...\) delimiters -
 *     see render_math() in main.js), since half a formula hands KaTeX
 *     something it can't render;
 *   - a <pre> block, which reads as garbage cut mid-line.
 * When it actually cuts something, it appends a "See More" link to the full
 * post. A description already under the budget renders in full with no link,
 * so this is a safe drop-in for PostBody wherever a preview is wanted.
 */
class TruncatedPostBody extends PostBody
{
    public const DEFAULT_MAX_LENGTH = 500;

    // Elements whose text is meaningless to cut mid-way: kept whole if they
    // fit, dropped whole (with everything after) if they don't, never split.
    private const ATOMIC_TAGS = ['pre'];

    // The KaTeX auto-render delimiters this app configures, opener => closer.
    // No single $...$ - the app deliberately doesn't enable it.
    private const MATH_DELIMITERS = [
        '$$' => '$$',
        '\\[' => '\\]',
        '\\(' => '\\)',
    ];

    private int $maxLength;
    private ?string $seeMoreURL;

    // Walk state, reset at the top of every toDOM().
    private int $remaining;
    private bool $didTruncate;
    // The closing delimiter we're waiting on while inside a formula, or null.
    private ?string $mathCloser;

    public function __construct(?string $see_more_url = null, int $max_length = self::DEFAULT_MAX_LENGTH)
    {
        parent::__construct();

        $this -> seeMoreURL = $see_more_url;
        $this -> maxLength = $max_length;
    }

    public function toDOM(): \DOMElement
    {
        // parent::toDOM() parses the raw description and runs it through
        // PostBody's whitelist, exactly as a full render would.
        $element = parent::toDOM();

        $this -> remaining = $this -> maxLength;
        $this -> didTruncate = false;
        $this -> mathCloser = null;

        $this -> truncateChildren($element);

        if ($this -> didTruncate && $this -> seeMoreURL !== null) {
            // A trailing child of the body, after all content.
            $element -> appendChild((new SeeMore($this -> seeMoreURL)) -> toDOM());
        }

        return $element;
    }

    private function truncateChildren(\DOMNode $parent): void
    {
        // Snapshot the list - we remove children as we go.
        foreach (iterator_to_array($parent -> childNodes) as $child) {
            // Budget spent and not mid-formula: drop this node and, since
            // nothing after it can fit either, every later sibling too.
            if ($this -> remaining <= 0 && $this -> mathCloser === null) {
                $parent -> removeChild($child);
                $this -> didTruncate = true;
                continue;
            }

            if ($child instanceof \DOMText) {
                $this -> truncateTextNode($child);
                continue;
            }

            if ($child instanceof \DOMElement) {
                if ($this -> mathCloser === null && in_array(strtolower($child -> tagName), self::ATOMIC_TAGS, true)) {
                    $length = mb_strlen($child -> textContent);

                    if ($length > $this -> remaining) {
                        // Won't fit whole and can't be split - drop it and stop.
                        $parent -> removeChild($child);
                        $this -> didTruncate = true;
                        $this -> remaining = 0;
                        continue;
                    }

                    $this -> remaining -= $length;
                    continue;
                }

                $had_children = $child -> hasChildNodes();
                $this -> truncateChildren($child);

                // An element emptied entirely by truncation is just a stray tag
                // in the preview - drop it. (Guard on had_children so a legit
                // empty element like <br> is never removed.)
                if ($had_children && !$child -> hasChildNodes()) {
                    $parent -> removeChild($child);
                }
            }
        }
    }

    private function truncateTextNode(\DOMText $node): void
    {
        $chars = mb_str_split((string) $node -> nodeValue);
        $count = count($chars);
        $kept = '';
        $kept_length = 0;
        // The length to fall back to on a cut: the last point that's a clean
        // boundary - after a space, or after a whole formula. Kept text past
        // this is a partial word (safe to drop); a completed formula is never
        // past it, so an included formula is never trimmed back off.
        $safe_length = 0;
        $cut = false;

        for ($i = 0; $i < $count;) {
            $pair = $i + 1 < $count ? $chars[$i] . $chars[$i + 1] : '';

            if ($this -> mathCloser !== null) {
                // Inside a formula: never cut, just walk to the closer.
                $kept .= $chars[$i];
                $kept_length += 1;
                $this -> remaining -= 1;

                if ($pair === $this -> mathCloser) {
                    $kept .= $chars[$i + 1];
                    $kept_length += 1;
                    $this -> remaining -= 1;
                    $this -> mathCloser = null;
                    $safe_length = $kept_length;
                    $i += 2;
                    continue;
                }

                $i += 1;
                continue;
            }

            if (isset(self::MATH_DELIMITERS[$pair])) {
                // A formula starts here. If the budget's already gone, cut
                // before it rather than committing to include it.
                if ($this -> remaining <= 0) {
                    $cut = true;
                    break;
                }

                $this -> mathCloser = self::MATH_DELIMITERS[$pair];
                $kept .= $pair;
                $kept_length += 2;
                $this -> remaining -= 2;
                $i += 2;
                continue;
            }

            if ($this -> remaining <= 0) {
                $cut = true;
                break;
            }

            $character = $chars[$i];
            $kept .= $character;
            $kept_length += 1;
            $this -> remaining -= 1;

            if (preg_match('/\s/u', $character) === 1) {
                $safe_length = $kept_length;
            }

            $i += 1;
        }

        if (!$cut) {
            return;
        }

        // Fall back to the last clean boundary. If there wasn't one (a single
        // word longer than the whole budget), keep the hard cut rather than
        // emptying the node.
        $bounded = $safe_length > 0 ? rtrim(mb_substr($kept, 0, $safe_length)) : rtrim($kept);

        $node -> nodeValue = $bounded . '...';
        $this -> remaining = 0;
        $this -> didTruncate = true;
    }
}
