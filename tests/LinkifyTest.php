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
}
