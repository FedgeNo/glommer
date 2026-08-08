<?php

declare(strict_types=1);

/**
 * Markup from elsewhere read into a delta. What matters is that a post keeps
 * what it was written with, that nesting the flat delta cannot hold is
 * flattened on purpose, and that nothing arrives with an attribute this site
 * does not allow - the last of those being Delta::sanitize's job, which every
 * conversion ends on.
 */
class HTMLToDeltaTest extends TestCase
{
    private const NEWLINE = "\n";

    /** @param array[] $ops */
    private function text(array $ops): string
    {
        return implode('', array_map(static fn (array $op): string => (string) $op['insert'], $ops));
    }

    /** @param array[] $ops */
    private function attributesOf(array $ops, string $insert): array
    {
        foreach ($ops as $op) {
            if ($op['insert'] === $insert) {
                return $op['attributes'] ?? [];
            }
        }

        return [];
    }

    public function testEmphasisSurvivesAsItsOwnRun(): void
    {
        $ops = HTMLToDelta::convert('<p>hello <strong>bold</strong> and <em>italic</em></p>');

        $this -> assertSame('hello bold and italic' . self::NEWLINE, $this -> text($ops));
        $this -> assertSame(['bold' => true], $this -> attributesOf($ops, 'bold'));
        $this -> assertSame(['italic' => true], $this -> attributesOf($ops, 'italic'));
    }

    public function testEachParagraphEndsItsOwnBlock(): void
    {
        $ops = HTMLToDelta::convert('<p>one</p><p>two</p>');

        $this -> assertSame('one' . self::NEWLINE . 'two' . self::NEWLINE, $this -> text($ops));
    }

    public function testABreakEndsALineToo(): void
    {
        $ops = HTMLToDelta::convert('<p>a<br>b</p>');

        $this -> assertSame('a' . self::NEWLINE . 'b' . self::NEWLINE, $this -> text($ops));
    }

    public function testBlockKindsLandOnTheNewlineThatEndsThem(): void
    {
        $quote = HTMLToDelta::convert('<blockquote><p>quoted</p></blockquote>');
        $this -> assertSame(['blockquote' => true], $this -> attributesOf($quote, self::NEWLINE));

        $bullets = HTMLToDelta::convert('<ul><li>first</li></ul>');
        $this -> assertSame(['list' => 'bullet'], $this -> attributesOf($bullets, self::NEWLINE));

        $numbered = HTMLToDelta::convert('<ol><li>one</li></ol>');
        $this -> assertSame(['list' => 'ordered'], $this -> attributesOf($numbered, self::NEWLINE));
    }

    /** Three is the smallest heading rendered here, so smaller ones become it. */
    public function testHeadingsClampToTheLevelsThisSiteHas(): void
    {
        $this -> assertSame(['header' => 1], $this -> attributesOf(HTMLToDelta::convert('<h1>Big</h1>'), self::NEWLINE));
        $this -> assertSame(['header' => 3], $this -> attributesOf(HTMLToDelta::convert('<h5>Small</h5>'), self::NEWLINE));
    }

    /**
     * Mastodon shortens a long link by hiding its scheme and tail behind
     * spans. That is its idea of tidy, not ours - a link here reads as the
     * whole address it leads to, so the hidden parts are taken like any other
     * words.
     */
    public function testAShortenedLinkIsPutBackTogetherWhole(): void
    {
        $ops = HTMLToDelta::convert(
            '<p><a href="https://example.test/verylong">'
            . '<span class="invisible">https://</span><span>example.test/very</span>'
            . '<span class="invisible">long</span></a></p>'
        );

        $this -> assertSame('https://example.test/verylong' . self::NEWLINE, $this -> text($ops));

        // One span per piece, so the address arrives as three runs rather than
        // one - every one of them part of the same link.
        foreach ($ops as $op) {
            if ($op['insert'] !== self::NEWLINE) {
                $this -> assertSame(['link' => 'https://example.test/verylong'], $op['attributes'] ?? []);
            }
        }
    }

    /**
     * The sanitizer every conversion ends on is what decides this, and it is
     * the reason nothing here needs its own idea of a safe scheme.
     */
    public function testAnUnsafeSchemeLosesTheLinkAndKeepsTheWords(): void
    {
        $ops = HTMLToDelta::convert('<p>bad <a href="javascript:alert(1)">link</a></p>');

        $this -> assertSame('bad link' . self::NEWLINE, $this -> text($ops));
        $this -> assertSame([], $this -> attributesOf($ops, 'link'));
    }

    public function testScriptContentIsNotWritingAndDoesNotBecomeAny(): void
    {
        $ops = HTMLToDelta::convert('<p>before</p><script>alert(1)</script><p>after</p>');

        $this -> assertSame('before' . self::NEWLINE . 'after' . self::NEWLINE, $this -> text($ops));
    }

    /** Inside a <pre> the spacing is the content and a newline ends a line. */
    public function testCodeKeepsItsOwnLines(): void
    {
        $ops = HTMLToDelta::convert('<pre><code>line one' . self::NEWLINE . 'line two</code></pre>');

        $this -> assertSame(
            ['code-block' => true],
            $this -> attributesOf($ops, self::NEWLINE)
        );
        $this -> assertSame('line one' . self::NEWLINE . 'line two' . self::NEWLINE, $this -> text($ops));
    }

    /** Ordinary markup whitespace is layout, not writing. */
    public function testIndentationBetweenTagsIsNotText(): void
    {
        $ops = HTMLToDelta::convert("<p>\n    one   two\n</p>");

        $this -> assertSame(' one two ' . self::NEWLINE, $this -> text($ops));
    }

    public function testNothingAtAllConvertsToNothing(): void
    {
        $this -> assertSame([], HTMLToDelta::convert(''));
        $this -> assertSame([], HTMLToDelta::convert('   '));
    }
}
