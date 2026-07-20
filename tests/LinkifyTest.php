<?php

declare(strict_types=1);

class LinkifyTest extends TestCase
{
    public function testTextLooksURLDetectsSchemeURL(): void
    {
        $this -> assertTrue(Linkify::textLooksURL('check out https://example.com/path for more'));
    }

    public function testTextLooksURLDetectsWwwPrefixed(): void
    {
        $this -> assertTrue(Linkify::textLooksURL('go to www.example.com now'));
    }

    public function testTextLooksURLDetectsBareDomainWithSlash(): void
    {
        $this -> assertTrue(Linkify::textLooksURL('see example.com/page for details'));
    }

    public function testTextLooksURLRejectsBareDomainWithoutSlash(): void
    {
        // No path slash - LOOKS_URL requires one for a bare (schemeless,
        // non-www) domain, so this reads as plain text, not a link.
        $this -> assertFalse(Linkify::textLooksURL('my email is user@example.com'));
    }

    public function testTextLooksURLRejectsPlainText(): void
    {
        $this -> assertFalse(Linkify::textLooksURL('just an ordinary sentence, nothing linky here'));
    }

    public function testLinkHostExtractsAndLowercasesHost(): void
    {
        $this -> assertSame('example.com', Linkify::linkHost('https://EXAMPLE.com/path'));
    }

    public function testLinkHostStripsUserinfo(): void
    {
        $this -> assertSame('example.com', Linkify::linkHost('https://user:pass@example.com/path'));
    }

    public function testLinkHostStripsPort(): void
    {
        $this -> assertSame('example.com', Linkify::linkHost('https://example.com:8443/path'));
    }

    public function testLinkHostReturnsNullForRelativeURL(): void
    {
        $this -> assertNull(Linkify::linkHost('/users/fedge/'));
    }

    public function testLinkHostReturnsNullForMailto(): void
    {
        $this -> assertNull(Linkify::linkHost('mailto:someone@example.com'));
    }

    public function testTokenizePlainTextIsOneTextSegment(): void
    {
        $segments = Linkify::tokenize('just plain text, nothing special');

        $this -> assertCount(1, $segments);
        $this -> assertSame('text', $segments[0]['type']);
        $this -> assertSame('just plain text, nothing special', $segments[0]['text']);
    }

    public function testTokenizeBareURLBecomesURLSegment(): void
    {
        $segments = Linkify::tokenize('link: https://example.com/path see');

        $this -> assertCount(3, $segments);
        $this -> assertSame('text', $segments[0]['type']);
        $this -> assertSame('url', $segments[1]['type']);
        $this -> assertSame('https://example.com/path', $segments[1]['text']);
        $this -> assertSame('text', $segments[2]['type']);
    }

    public function testTokenizeHashtagBecomesHashtagSegmentWithLowercasedTag(): void
    {
        $segments = Linkify::tokenize('great #Cats content');

        $this -> assertCount(3, $segments);
        $this -> assertSame('hashtag', $segments[1]['type']);
        $this -> assertSame('#Cats', $segments[1]['text']);
        $this -> assertSame('cats', $segments[1]['tag']);
    }

    public function testTokenizeAllNumericHashtagIsNotLinkified(): void
    {
        // classify() requires at least one letter in the tag body - a bare
        // year like #2024 has none, so it's left as plain text.
        $segments = Linkify::tokenize('happy #2024 everyone');

        foreach ($segments as $segment) {
            $this -> assertFalse($segment['type'] === 'hashtag', 'a numeric-only tag should never linkify');
        }
    }

    public function testTokenizeTrimsTrailingPunctuationOffURL(): void
    {
        // The trimmed "." becomes its own trailing text segment - it has no
        // adjacent text segment to merge into here (the URL sits between it
        // and the leading "see "), so this is 3 segments, not 2.
        $segments = Linkify::tokenize('see https://example.com/page.');

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
        $segments = Linkify::tokenize('see https://example.com/page. thanks!');

        $text_segments = array_values(array_filter($segments, fn ($s) => $s['type'] === 'text'));

        // "see ", then the merged "." + " thanks!" - never split into more
        // than these two text runs around the one URL.
        $this -> assertCount(2, $text_segments);
        $this -> assertSame('. thanks!', $text_segments[1]['text']);
    }

    public function testTokenizeHashInsideURLIsNotATag(): void
    {
        $segments = Linkify::tokenize('https://example.com/page#section');

        $this -> assertCount(1, $segments);
        $this -> assertSame('url', $segments[0]['type']);
        $this -> assertSame('https://example.com/page#section', $segments[0]['text']);
    }
    public function testTokenizeRemoteHandleBecomesAMentionOfTheFullHandle(): void
    {
        $segments = Linkify::tokenize('hi @bob@site.com');

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
            foreach (Linkify::tokenize($text) as $segment) {
                $this -> assertFalse($segment['type'] === 'mention');
            }
        }
    }

    public function testTokenizeRemoteMentionIsCaseFoldedForTheLink(): void
    {
        $segments = Linkify::tokenize('@Bob@Mastodon.Social');

        $this -> assertSame('bob@mastodon.social', $segments[0]['username']);
    }

    public function testTokenizeRemoteMentionKeepsATrailingSentencePeriodOutOfTheHandle(): void
    {
        $segments = Linkify::tokenize('@bob@site.com.');

        $this -> assertSame('bob@site.com', $segments[0]['username']);
    }

    public function testTokenizeHandleWithoutADottedHostFallsBackToTheLocalMention(): void
    {
        $segments = Linkify::tokenize('@bob@nodot');

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
        ];

        $php_output = array_map(static fn (string $text): array => Linkify::tokenize($text), $cases);

        $js_output = $this -> tokenizeWithNode($cases);

        if ($js_output === null) {
            $php = (string) file_get_contents(__DIR__ . '/../src/classes/Linkify.php');
            $js = (string) file_get_contents(__DIR__ . '/../delta.js');

            preg_match('/private const SCAN = "(.*)";/', $php, $php_match);
            preg_match('/const LINKIFY_SCAN = "(.*)";/', $js, $js_match);

            $this -> assertSame($php_match[1] ?? 'php', $js_match[1] ?? 'js');

            return;
        }

        $this -> assertSame(json_encode($php_output), json_encode($js_output));
    }

    /**
     * Runs delta.js's linkify_tokenize over each input under node. Null when
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
            . 'const src = fs.readFileSync(' . json_encode(__DIR__ . '/../delta.js') . ', "utf8");'
            . 'const tokenize = new Function(src + "; return linkify_tokenize;")();'
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
        $this -> assertSame(255, Linkify::MAX_MENTION_LENGTH);

        $js = (string) file_get_contents(__DIR__ . '/../delta.js');
        preg_match('/const LINKIFY_MAX_MENTION_LENGTH = (\\d+);/', $js, $match);

        $this -> assertSame((string) Linkify::MAX_MENTION_LENGTH, $match[1]);
    }


}
