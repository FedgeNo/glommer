<?php

declare(strict_types=1);

class LinkifierTest extends TestCase
{
    public function testTextLooksURLDetectsSchemeURL(): void
    {
        $this -> assertTrue(Linkifier::textLooksURL('check out https://example.com/path for more'));
    }

    public function testTextLooksURLDetectsWwwPrefixed(): void
    {
        $this -> assertTrue(Linkifier::textLooksURL('go to www.example.com now'));
    }

    public function testTextLooksURLDetectsBareDomainWithSlash(): void
    {
        $this -> assertTrue(Linkifier::textLooksURL('see example.com/page for details'));
    }

    public function testTextLooksURLRejectsBareDomainWithoutSlash(): void
    {
        // No path slash - LOOKS_URL requires one for a bare (schemeless,
        // non-www) domain, so this reads as plain text, not a link.
        $this -> assertFalse(Linkifier::textLooksURL('my email is user@example.com'));
    }

    public function testTextLooksURLRejectsPlainText(): void
    {
        $this -> assertFalse(Linkifier::textLooksURL('just an ordinary sentence, nothing linky here'));
    }

    public function testLinkHostExtractsAndLowercasesHost(): void
    {
        $this -> assertSame('example.com', Linkifier::linkHost('https://EXAMPLE.com/path'));
    }

    public function testLinkHostStripsUserinfo(): void
    {
        $this -> assertSame('example.com', Linkifier::linkHost('https://user:pass@example.com/path'));
    }

    public function testLinkHostStripsPort(): void
    {
        $this -> assertSame('example.com', Linkifier::linkHost('https://example.com:8443/path'));
    }

    public function testLinkHostReturnsNullForRelativeURL(): void
    {
        $this -> assertNull(Linkifier::linkHost('/users/fedge/'));
    }

    public function testLinkHostReturnsNullForMailto(): void
    {
        $this -> assertNull(Linkifier::linkHost('mailto:someone@example.com'));
    }

    public function testTokenizePlainTextIsOneTextSegment(): void
    {
        $segments = Linkifier::tokenize('just plain text, nothing special');

        $this -> assertCount(1, $segments);
        $this -> assertSame('text', $segments[0]['type']);
        $this -> assertSame('just plain text, nothing special', $segments[0]['text']);
    }

    public function testTokenizeBareURLBecomesURLSegment(): void
    {
        $segments = Linkifier::tokenize('link: https://example.com/path see');

        $this -> assertCount(3, $segments);
        $this -> assertSame('text', $segments[0]['type']);
        $this -> assertSame('url', $segments[1]['type']);
        $this -> assertSame('https://example.com/path', $segments[1]['text']);
        $this -> assertSame('text', $segments[2]['type']);
    }

    public function testTokenizeHashtagBecomesHashtagSegmentWithLowercasedTag(): void
    {
        $segments = Linkifier::tokenize('great #Cats content');

        $this -> assertCount(3, $segments);
        $this -> assertSame('hashtag', $segments[1]['type']);
        $this -> assertSame('#Cats', $segments[1]['text']);
        $this -> assertSame('cats', $segments[1]['tag']);
    }

    /**
     * A tag is not an English word. These have to survive the byte-level
     * scanner, and the tag they resolve to has to be what the browser's mirror
     * resolves the same text to - the parity cases live in
     * tests/js/LinkifierTest.js against this same list.
     */
    public function testTokenizeTagsBeyondASCII(): void
    {
        $accented = Linkifier::tokenize('un #café');
        $this -> assertSame('hashtag', $accented[1]['type']);
        $this -> assertSame('café', $accented[1]['tag']);

        $cjk = Linkifier::tokenize('read #日本語 here');
        $this -> assertSame('hashtag', $cjk[1]['type']);
        $this -> assertSame('日本語', $cjk[1]['tag']);
    }

    /**
     * Lowercasing has to know about more than ASCII, or #CAFÉ and #café are
     * two tags here and one in the browser.
     */
    public function testATagIsLowercasedBeyondASCIIToo(): void
    {
        $segments = Linkifier::tokenize('#CAFÉ');

        $this -> assertSame('café', $segments[0]['tag']);
    }

    /** The text on either side still has to come back whole. */
    public function testTextAroundANonASCIITagSurvivesIntact(): void
    {
        $segments = Linkifier::tokenize('vor #Ünicode nach');

        $this -> assertSame('vor ', $segments[0]['text']);
        $this -> assertSame('#Ünicode', $segments[1]['text']);
        $this -> assertSame('ünicode', $segments[1]['tag']);
        $this -> assertSame(' nach', $segments[2]['text']);
    }

    /**
     * The boundary in front of a tag has to hold for an accented word too -
     * on this side that means a UTF-8 continuation byte must not read as the
     * punctuation that would let a tag start.
     */
    public function testAnAccentedWordBeforeAHashDoesNotStartATag(): void
    {
        foreach (Linkifier::tokenize('café#nope') as $segment) {
            $this -> assertFalse($segment['type'] === 'hashtag');
        }
    }

    /** The cap counts characters, not the bytes they happen to take. */
    public function testTheLengthCapCountsCharacters(): void
    {
        $this -> assertTrue(Linkifier::isTagSlug(str_repeat('é', Linkifier::MAX_TAG_LENGTH)));
        $this -> assertFalse(Linkifier::isTagSlug(str_repeat('é', Linkifier::MAX_TAG_LENGTH + 1)));
    }

    public function testTokenizeAllNumericHashtagIsNotLinkified(): void
    {
        // classify() requires at least one letter in the tag body - a bare
        // year like #2024 has none, so it's left as plain text.
        $segments = Linkifier::tokenize('happy #2024 everyone');

        foreach ($segments as $segment) {
            $this -> assertFalse($segment['type'] === 'hashtag', 'a numeric-only tag should never linkify');
        }
    }

    public function testTokenizeTrimsTrailingPunctuationOffURL(): void
    {
        // The trimmed "." becomes its own trailing text segment - it has no
        // adjacent text segment to merge into here (the URL sits between it
        // and the leading "see "), so this is 3 segments, not 2.
        $segments = Linkifier::tokenize('see https://example.com/page.');

        $this -> assertCount(3, $segments);
        $this -> assertSame('url', $segments[1]['type']);
        $this -> assertSame('https://example.com/page', $segments[1]['text']);
        $this -> assertSame('text', $segments[2]['type']);
        $this -> assertSame('.', $segments[2]['text']);
    }

    public function testTokenizeMergesAdjacentTextSegments(): void
    {
        // The trailing "." trimmed off the URL above becomes its own text
        // segment internally - mergeText() must fold it back into the
        // following text rather than leaving two separate text nodes.
        $segments = Linkifier::tokenize('see https://example.com/page. thanks!');

        $text_segments = array_values(array_filter($segments, fn ($s) => $s['type'] === 'text'));

        // "see ", then the merged "." + " thanks!" - never split into more
        // than these two text runs around the one URL.
        $this -> assertCount(2, $text_segments);
        $this -> assertSame('. thanks!', $text_segments[1]['text']);
    }

    public function testTokenizeHashInsideURLIsNotATag(): void
    {
        $segments = Linkifier::tokenize('https://example.com/page#section');

        $this -> assertCount(1, $segments);
        $this -> assertSame('url', $segments[0]['type']);
        $this -> assertSame('https://example.com/page#section', $segments[0]['text']);
    }
    public function testTokenizeRemoteHandleBecomesAMentionOfTheFullHandle(): void
    {
        $segments = Linkifier::tokenize('hi @bob@site.com');

        $this -> assertSame('mention', $segments[1]['type']);
        $this -> assertSame('bob@site.com', $segments[1]['username']);
        $this -> assertSame('@bob@site.com', $segments[1]['text']);
    }

    /**
     * A bare email address is not a mention. Only an explicit leading @ makes
     * one, which is what the pattern's leading boundary enforces.
     */
    public function testTokenizeBareEmailIsNeverAMention(): void
    {
        foreach (['bob@site.com', 'email me at bob@site.com ok', 'a.b@sub.domain.co.uk'] as $text) {
            foreach (Linkifier::tokenize($text) as $segment) {
                $this -> assertFalse($segment['type'] === 'mention');
            }
        }
    }

    public function testTokenizeRemoteMentionIsCaseFoldedForTheLink(): void
    {
        $segments = Linkifier::tokenize('@Bob@Mastodon.Social');

        $this -> assertSame('bob@mastodon.social', $segments[0]['username']);
    }

    public function testTokenizeRemoteMentionKeepsATrailingSentencePeriodOutOfTheHandle(): void
    {
        $segments = Linkifier::tokenize('@bob@site.com.');

        $this -> assertSame('bob@site.com', $segments[0]['username']);
    }

    public function testTokenizeHandleWithoutADottedHostFallsBackToTheLocalMention(): void
    {
        $segments = Linkifier::tokenize('@bob@nodot');

        $this -> assertSame('bob', $segments[0]['username']);
    }

    /**
     * PHP and JavaScript must tokenize identically - the same post text is
     * rendered by DeltaRenderer on the server and by delta.js on the client,
     * and a disagreement shows up as content that changes when the page
     * reloads.
     *
     * This runs delta.js's own tokenizer under node and compares its output to
     * PHP's, so it catches a real behavioural divergence rather than only a
     * textual one. Where node isn't available it falls back to comparing the
     * shared scanner string, which is weaker but still fails on the most
     * likely mistake: editing one copy and not the other.
     */
    public function testJavaScriptTokenizesIdenticallyToPHP(): void
    {
        $cases = [
            'hi @bob@site.com and @alice',
            'bob@site.com is an email',
            'see https://example.com/a#b and #tag',
            '@Bob@Mastodon.Social said so',
            '@bob@site.com.',
            '@bob@nodot',
            'plain text with nothing in it',
            'a#b ##c @@d',
            // Beyond ASCII, where the two engines could most easily disagree:
            // this side scans bytes and slices by byte offset, the other scans
            // code points and slices by UTF-16 offset.
            'un #café et #Ünicode',
            'read #日本語 here',
            '#CAFÉ shouting',
            'café#nope',
            'emoji #ok🎉 tail',
            // Written without a scheme, which is how most links read on the
            // Fediverse - and the sentences that only look like one.
            'see example.com/thing here',
            'visit www.example.com today',
            'e.g. that is all and Node.js is fine',
            'at example.com/a. Next',
            // Punctuation a tag collects off its end. Byte-length here against
            // UTF-16 length there, over multibyte marks, is exactly where the
            // two could slice differently and produce different links for one
            // post depending on whether it arrived in the page or by scroll.
            'a story about #Brazil…',
            'cut short at #…',
            'he said #Brazil” and #Brazil—',
            'in #Köln… and #café…',
        ];

        $php_output = array_map(static fn (string $text): array => Linkifier::tokenize($text), $cases);

        $js_output = $this -> tokenizeWithNode($cases);

        if ($js_output === null) {
            // No node to run the other side with, so fall back to proving the
            // two patterns are still the same text. They are written as an
            // expression rather than one literal, so the language's own way of
            // naming and joining the parts is normalised away first.
            $php = (string) file_get_contents(__DIR__ . '/../src/classes/Linkifier.php');
            $js = (string) file_get_contents(__DIR__ . '/../scripts/Linkifier.js');

            preg_match('/private const TAG_CHARS = "(.*)";/', $php, $php_tag_chars);
            preg_match('/static TAG_CHARS = "(.*)";/', $js, $js_tag_chars);

            $this -> assertSame($php_tag_chars[1] ?? 'php', $js_tag_chars[1] ?? 'js');

            preg_match('/private const SCAN = (.*);/', $php, $php_match);
            preg_match('/static SCAN = (.*);/', $js, $js_match);

            $normalize = static fn (string $expression): string => str_replace(
                ['self::TAG_CHARS', 'Linkifier.TAG_CHARS', ' . ', ' + '],
                ['TAG_CHARS', 'TAG_CHARS', ' JOIN ', ' JOIN '],
                $expression
            );

            $this -> assertSame($normalize($php_match[1] ?? 'php'), $normalize($js_match[1] ?? 'js'));

            return;
        }

        $this -> assertSame(json_encode($php_output), json_encode($js_output));
    }

    /**
     * Runs Linkifier.js's Linkifier.tokenize over each input under node. Null when
     * node isn't installed, so the suite still runs on a box without it.
     *
     * @param string[] $cases
     * @return array[]|null
     */
    private function tokenizeWithNode(array $cases): ?array
    {
        if (trim((string) shell_exec('command -v node 2>/dev/null')) === '') {
            return null;
        }

        $script = 'const fs = require("fs");'
            . 'const src = fs.readFileSync(' . json_encode(__DIR__ . '/../scripts/Linkifier.js') . ', "utf8");'
            . 'const tokenize = new Function(src.replace(/^export /gm, "") + "; return Linkifier.tokenize;")();'
            . 'const cases = ' . json_encode($cases) . ';'
            . 'process.stdout.write(JSON.stringify(cases.map((t) => tokenize(t))));';

        $output = shell_exec('node -e ' . escapeshellarg($script) . ' 2>/dev/null');
        $decoded = json_decode((string) $output, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Users.slug is wide enough to hold a whole Fediverse handle, so a mention
     * has to be able to be that long too - a shorter cap here would silently
     * make long handles unmentionable.
     */
    public function testMentionLengthCapCoversAWholeHandle(): void
    {
        $this -> assertSame(255, Linkifier::MAX_MENTION_LENGTH);

        $js = (string) file_get_contents(__DIR__ . '/../scripts/Linkifier.js');
        preg_match('/static MAX_MENTION_LENGTH = (\\d+);/', $js, $match);

        $this -> assertSame((string) Linkifier::MAX_MENTION_LENGTH, $match[1]);
    }

    /**
     * A tag may be written in any script, which is why tag characters are
     * stated as the ASCII they exclude - and that lets an ellipsis in with
     * them. Truncating a post appends one and the text is linkified after, so
     * a tag left at the cut became a link to a page that does not exist.
     */
    public function testAnEllipsisIsNotPartOfTheTagItLandsOn(): void
    {
        $segments = Linkifier::tokenize('a story about #Brazil…');

        $this -> assertSame('hashtag', $segments[1]['type']);
        $this -> assertSame('brazil', $segments[1]['tag']);
        $this -> assertSame('#Brazil', $segments[1]['text']);
        $this -> assertSame('…', $segments[2]['text']);
    }

    /** And where the cut leaves nothing but the mark, there is no tag at all. */
    public function testAHashWithNothingButAnEllipsisIsNotATag(): void
    {
        $segments = Linkifier::tokenize('cut short at #…');

        foreach ($segments as $segment) {
            $this -> assertSame('text', $segment['type']);
        }

        $this -> assertSame('cut short at #…', implode('', array_column($segments, 'text')));
    }

    /** The same for the other marks a sentence ends a tag with. */
    public function testASmartQuoteOrDashDoesNotJoinTheTag(): void
    {
        $this -> assertSame('brazil', Linkifier::tokenize('he said #Brazil”')[1]['tag']);
        $this -> assertSame('brazil', Linkifier::tokenize('he said #Brazil—')[1]['tag']);
    }

    /**
     * The whole reason tag characters are permissive: a tag in another script
     * is a tag. Trimming punctuation must not reach into one.
     */
    public function testANonASCIITagIsUntouched(): void
    {
        $this -> assertSame('café', Linkifier::tokenize('at the #café')[1]['tag']);
        $this -> assertSame('日本語', Linkifier::tokenize('in #日本語')[1]['tag']);
        $this -> assertSame('köln', Linkifier::tokenize('in #Köln…')[1]['tag']);
    }
}
