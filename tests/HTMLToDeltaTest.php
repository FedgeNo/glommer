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
     * A hashtag arrives linked to the tag page of the server that wrote the
     * post. #cats is a subject, and the subject on this site is this site's
     * own page for it - so the link is dropped and the words come out plain,
     * for the renderer's tokenizer to link here as it does for a tag typed
     * here.
     */
    public function testAHashtagLosesTheOriginServersLink(): void
    {
        $ops = HTMLToDelta::convert(
            '<p>Look at <a href="https://mastodon.social/tags/cats" class="mention hashtag" rel="tag">#<span>cats</span></a> today</p>'
        );

        $this -> assertSame('Look at #cats today' . self::NEWLINE, $this -> text($ops));

        foreach ($ops as $op) {
            $this -> assertFalse(isset($op['attributes']['link']), 'a hashtag should carry no link of its own');
        }
    }

    /**
     * A mention keeps its own. It travels as "@user" without the domain, so
     * relinking one here would point at whoever happens to share the name, or
     * at nobody at all.
     */
    public function testAMentionKeepsTheLinkToWhoeverItNames(): void
    {
        $ops = HTMLToDelta::convert('<p>hi <a href="https://mastodon.social/@bob" class="u-url mention">@<span>bob</span></a></p>');

        $this -> assertSame(
            ['link' => 'https://mastodon.social/@bob'],
            $this -> attributesOf($ops, '@bob')
        );
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

    /** What RemoteMentions::localProfiles() hands over: handle => page here. */
    private const MENTIONS = [
        'alice' => 'https://glommer.test/users/alice%40mastodon.social/',
        'alice@mastodon.social' => 'https://glommer.test/users/alice%40mastodon.social/',
    ];

    /**
     * A mention arriving as bare words leads to the account it names.
     *
     * Without this it reads as a local @alice - a link to whoever here happens
     * to share the name, or to a profile that does not exist. The words are
     * left exactly as written; only the link knows the difference.
     */
    public function testABareMentionLeadsToTheAccountItNames(): void
    {
        $ops = HTMLToDelta::convert('<p>morning @alice how are you</p>', self::MENTIONS);

        $this -> assertSame('morning @alice how are you' . self::NEWLINE, $this -> text($ops));
        $this -> assertSame(
            ['link' => self::MENTIONS['alice']],
            $this -> attributesOf($ops, '@alice'),
            'the short form still gets the long treatment in its link'
        );
    }

    /** The same words, when nothing said who they meant, stay words. */
    public function testAMentionNobodyExplainedIsLeftAlone(): void
    {
        $ops = HTMLToDelta::convert('<p>morning @stranger how are you</p>', self::MENTIONS);

        $this -> assertSame('morning @stranger how are you' . self::NEWLINE, $this -> text($ops));
        $this -> assertSame([], $this -> attributesOf($ops, '@stranger'));
    }

    /** Written in full, it resolves the same way. */
    public function testAFullHandleResolvesToo(): void
    {
        $ops = HTMLToDelta::convert('<p>ask @alice@mastodon.social about it</p>', self::MENTIONS);

        $this -> assertSame(
            ['link' => self::MENTIONS['alice']],
            $this -> attributesOf($ops, '@alice@mastodon.social')
        );
    }

    /**
     * A mention the far server anchored itself leads here as well, so the two
     * forms of one mention do not go to two different places.
     */
    public function testAnAnchoredMentionIsBroughtHomeToo(): void
    {
        $ops = HTMLToDelta::convert(
            '<p>morning <a href="https://mastodon.social/@alice">@alice</a></p>',
            self::MENTIONS
        );

        $this -> assertSame(
            ['link' => self::MENTIONS['alice']],
            $this -> attributesOf($ops, '@alice')
        );
    }

    /**
     * A hashtag is never a person, however many people share the name.
     *
     * It arrives linked at the writer's own tag page, which this strips so the
     * words become a tag link here - and a server writing the # and the word as
     * separate nodes hands the word over on its own. Only a piece that says @
     * addresses anybody.
     */
    public function testAHashtagSharingAUsernameIsStillAHashtag(): void
    {
        $mentions = ['startrek' => 'https://glommer.test/users/startrek%40fedigroups.social/'];

        $ops = HTMLToDelta::convert(
            '<p>about <a href="https://host/tags/startrek">#<span>startrek</span></a> today</p>',
            $mentions
        );

        $this -> assertSame('about #startrek today' . self::NEWLINE, $this -> text($ops));

        foreach ($ops as $op) {
            $this -> assertFalse(
                isset($op['attributes']['link']),
                'the tag was linked at a person: ' . json_encode($op)
            );
        }
    }

    /** An ordinary link is still the writer's own, mentions or not. */
    public function testAnOrdinaryLinkIsUntouched(): void
    {
        $ops = HTMLToDelta::convert(
            '<p>see <a href="https://example.test/thing">this thing</a></p>',
            self::MENTIONS
        );

        $this -> assertSame(
            ['link' => 'https://example.test/thing'],
            $this -> attributesOf($ops, 'this thing')
        );
    }

    /** With nothing to resolve against, the words are words - as before. */
    public function testWithoutMentionsNothingIsRewritten(): void
    {
        $ops = HTMLToDelta::convert('<p>morning @alice</p>');

        $this -> assertSame('morning @alice' . self::NEWLINE, $this -> text($ops));
        $this -> assertSame([], $this -> attributesOf($ops, '@alice'));
    }
}
