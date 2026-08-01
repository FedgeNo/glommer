<?php

declare(strict_types=1);

/**
 * Shortcodes expand at the last step of output and nowhere else.
 *
 * A shortcode is a local convenience, not a Fediverse format: ActivityPub
 * carries emoji as literal characters and nothing on the receiving side expands
 * :smile: for us. So the federated copy has to already be an emoji, while what
 * is stored stays exactly as typed.
 */
class EmojiShortcodeTest extends TestCase
{
    public function testAKnownShortcodeBecomesItsEmoji(): void
    {
        $this -> assertSame('hello 😄 world', EmojiShortcode::expand('hello :smile: world'));
    }

    public function testAnUnknownNameIsLeftAlone(): void
    {
        // Which is what leaves room for a custom emoji: it travels as its own
        // name plus a per-post Emoji tag, and must survive to be resolved.
        $this -> assertSame('a :blobcat: b', EmojiShortcode::expand('a :blobcat: b'));
    }

    public function testATimeIsNotAnEmoji(): void
    {
        $this -> assertSame('meet at 12:30:45', EmojiShortcode::expand('meet at 12:30:45'));
    }

    public function testARatioIsNotAnEmoji(): void
    {
        $this -> assertSame('odds of 3:4 and 1:2', EmojiShortcode::expand('odds of 3:4 and 1:2'));
    }

    public function testAdjacentShortcodesBothExpand(): void
    {
        $this -> assertSame('🐱🐶', EmojiShortcode::expand(':cat::dog:'));
    }

    public function testNamesAreMatchedWithoutRegardToCase(): void
    {
        $this -> assertSame('😄', EmojiShortcode::expand(':SMILE:'));
    }

    public function testTextWithNoColonsIsReturnedUnchanged(): void
    {
        $this -> assertSame('nothing to do here', EmojiShortcode::expand('nothing to do here'));
    }

    public function testTheTableHoldsTheCommonOnes(): void
    {
        // A spot check that the generated table is actually the GitHub set
        // rather than something that merely parsed.
        foreach ([':smile:', ':+1:', ':fire:', ':100:', ':tada:'] as $shortcode) {
            $this -> assertFalse(
                EmojiShortcode::expand($shortcode) === $shortcode,
                $shortcode . ' should be a known shortcode'
            );
        }
    }

    public function testAPostBodyExpandsButItsCodeDoesNot(): void
    {
        $html = DeltaRenderer::toHTML([
            ['insert' => 'say :smile: and '],
            ['insert' => ':smile:', 'attributes' => ['code' => true]],
            ['insert' => "\n"],
        ]);

        $this -> assertTrue(str_contains($html, 'say 😄 and'), 'prose should expand');
        $this -> assertTrue(str_contains($html, '<code>:smile:</code>'), 'inline code should not');
    }

    public function testACodeBlockKeepsItsColons(): void
    {
        // The reason expansion walks the finished tree: a code block is marked
        // on the line that ends it, so mid-build there is no way to know.
        $html = DeltaRenderer::toHTML([
            ['insert' => 'x = :smile:'],
            ['insert' => "\n", 'attributes' => ['code-block' => true]],
        ]);

        $this -> assertTrue(str_contains($html, '<pre>x = :smile:</pre>'));
    }

    public function testFormattingDoesNotStopExpansion(): void
    {
        $html = DeltaRenderer::toHTML([
            ['insert' => ':fire:', 'attributes' => ['bold' => true]],
            ['insert' => "\n"],
        ]);

        $this -> assertTrue(str_contains($html, '<strong>🔥</strong>'));
    }

    public function testTheTwoTablesAgree(): void
    {
        // Generated together from one source, so a drift here means somebody
        // hand-edited one of them.
        $js = (string) file_get_contents(__DIR__ . '/../scripts/EmojiShortcodeMap.js');

        preg_match_all("/^    '([^']+)':/m", $js, $matches);

        // Cast to strings first: PHP turns a canonical numeric array key into
        // an integer, so ':100:' and ':-1:' arrive here as ints. Lookups are
        // unaffected - PHP normalises a numeric string key the same way on
        // read - but a raw comparison against the JS names would fail on them.
        $php_names = array_map('strval', array_keys(EmojiShortcodeMap::MAP));

        $this -> assertSame(count($php_names), count($matches[1]));
        $this -> assertSame($php_names, $matches[1]);
    }
}
