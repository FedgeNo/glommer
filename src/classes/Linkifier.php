<?php

declare(strict_types=1);

/**
 * The shared link/hashtag logic for the render pass, kept byte-for-byte in step
 * with the JS mirror in Linkifier.js (the two renderers must produce identical DOM).
 *
 * Everything here is pinned for PHP/JS parity: ASCII-only character classes (no
 * \s/\w/\b, which differ between PCRE and JS), no /u or /i flag (also divergent),
 * one combined URL-or-hashtag-or-mention scan run left-to-right with the URL
 * alternative first, and byte offsets sliced with byte-based substr (the JS side
 * uses UTF-16 offsets sliced with slice - same result because the classes never
 * match a multibyte byte or a surrogate).
 *
 * Two passes over a post's Delta:
 *   - textLooksURL(): pass 1's anti-phishing detector. A run that carries a link
 *     attribute AND whose text reads as a URL to a human gets that link stripped
 *     (see DeltaRenderer), so the visible URL can't hide a different destination.
 *     Deliberately broader than the linkifier and its output is just "plain
 *     text", so it can be looser - but PHP and JS must still agree.
 *   - tokenize(): pass 2. Splits plain text into [type,text] segments - bare
 *     URLs become self-links, #hashtags become tag links, @mentions become
 *     profile links, the rest stays text.
 */
class Linkifier
{
    // Longest tag we linkify / store. Enforced in code, not the regex, so the
    // pattern body stays free of {} and can share one delimiter with JS.
    public const MAX_TAG_LENGTH = 50;

    // Longest @mention username we linkify. Tracks Users.slug's width, since a
    // mention that can't be as long as a username simply couldn't address one -
    // a remote account's slug is its whole user@host handle. Independent of
    // MAX_TAG_LENGTH, which is a different concept that happens to be a number.
    public const MAX_MENTION_LENGTH = 255;

    // Trailing chars trimmed off a matched URL back into following text, so a
    // sentence's "...at https://x.com." doesn't swallow the period (or a wrapping
    // ")"). A URL that legitimately ends in one of these loses it - accepted.
    private const URL_TRAILING_TRIM = '.,!?;:)';

    // The pass-2 scanner: an http(s) URL, OR a #hashtag preceded by a boundary
    // (not a word char or another #, so a#b and ##b don't tag), OR an @mention
    // preceded by a boundary. URL first so a '#'/'@' inside a URL (its
    // character class already allows '@', e.g. userinfo@host) never starts a
    // hashtag/mention of its own.
    //
    // A mention is @name or @name@host.tld - the second form addresses a
    // Fediverse account, whose slug IS that handle, so the captured username
    // needs no translation to become a profile link. The host half must carry
    // a dot and end on an alphanumeric run, which both keeps "@name@" from
    // half-matching and leaves a sentence's trailing period outside the match.
    //
    // The leading boundary is what keeps a bare email address out: in
    // "bob@site.com" the @ is preceded by a word character, so it never starts
    // a mention. Only an explicit leading @ does.
    //
    // Shared verbatim with Linkifier.js via the same string; only the delimiter
    // differs (PHP {} vs JS new RegExp). No {} in the body so the {} delimiter
    // is safe.
    // What a #tag may be made of, stated as the ASCII it may NOT be made of:
    // everything below the digits, the punctuation between them and the
    // letters, and the rest up to DEL - leaving letters, digits, underscore,
    // and, by omission, every character above ASCII. Written that way on
    // purpose. A positive range would have to name the high characters, and
    // "\\x80-\\xFF" does not mean the same thing on both sides - PHP reads
    // bytes, so it covers a whole UTF-8 sequence, while JS reads code points
    // and would stop at U+00FF, tagging Latin-1 and refusing everything else.
    // Excluding ASCII says "anything else" identically to both.
    //
    // No /u flag either, for a worse divergence than the one it would fix:
    // PCRE returns no match at all from a subject that isn't valid UTF-8, so
    // one bad byte in a post would drop every link in it here while the
    // browser linkified them all.
    private const TAG_CHARS = "[^\\x00-\\x2F\\x3A-\\x40\\x5B-\\x5E\\x60\\x7B-\\x7F]";

    // The '#' is inside that excluded range, so it reads as a boundary and
    // "##b" would tag - hence the second lookbehind naming it.
    // The characters a URL may be spelled with, once one has started.
    private const URL_CHARS = "[A-Za-z0-9._~:/?#\\[\\]@!$&'()*+,;=%-]";

    // A link written without its scheme, which is how most of them read on the
    // Fediverse. Kept narrow, because a bare host is also what an ordinary
    // sentence is full of: either it says www, or it carries a path, and its
    // last label is letters - so "example.com/thing" and "www.example.com" are
    // links while "e.g." and "Node.js" are words. Two letters minimum rather
    // than a counted repeat, since {} is this pattern's own delimiter.
    private const BARE_URL = "(?<![A-Za-z0-9._~:/?#@-])(?:www\\.[A-Za-z0-9-]+(?:\\.[A-Za-z0-9-]+)*|[A-Za-z0-9-]+(?:\\.[A-Za-z0-9-]+)*\\.[A-Za-z][A-Za-z]+/)";

    // The '#' is inside that excluded range, so it reads as a boundary and
    // "##b" would tag - hence the second lookbehind naming it.
    private const SCAN = "https?://" . self::URL_CHARS . "+|" . self::BARE_URL . self::URL_CHARS . "*|(?<!" . self::TAG_CHARS . ")(?<!#)#" . self::TAG_CHARS . "+|(?<![A-Za-z0-9_@])@[A-Za-z0-9_]+(?:@[A-Za-z0-9-]+(?:\\.[A-Za-z0-9-]+)+)?";

    // Pass-1 detector: an http(s) URL, a www.-prefixed host, or a bare
    // domain.tld/ (with a path slash) - the shapes a human reads as a link.
    private const LOOKS_URL = 'https?://|www\\.[A-Za-z0-9-]|[A-Za-z0-9-]+\\.[A-Za-z][A-Za-z]+/';

    // Extracts a URL's authority (userinfo@host:port). Shared with Linkifier.js so
    // internal-vs-external is decided identically without PHP parse_url / JS URL
    // differences (default-port, scheme-relative, userinfo all handled here).
    private const AUTHORITY = '^(?:[A-Za-z][A-Za-z0-9+.-]*:)?//([^/?#]*)';

    /**
     * Whether text reads as containing a URL (pass 1's anti-phishing gate).
     */
    public static function textLooksURL(string $text): bool
    {
        return preg_match('{' . self::LOOKS_URL . '}', $text) === 1;
    }

    /**
     * The lowercased host of a URL, or null when it has no `//authority` (a
     * relative URL or a mailto: - both "internal", same-window). Control chars
     * are stripped first, matching how a browser parses a URL (and isSafeLink).
     * userinfo and port are dropped so `user@host` and `host:443` compare by host.
     */
    public static function linkHost(string $url): ?string
    {
        $stripped = preg_replace('/[\x00-\x20]+/', '', $url);

        if (preg_match('{' . self::AUTHORITY . '}', $stripped, $match) !== 1) {
            return null;
        }

        $authority = $match[1];
        $at = strrpos($authority, '@');

        if ($at !== false) {
            $authority = substr($authority, $at + 1);
        }

        $colon = strpos($authority, ':');

        if ($colon !== false) {
            $authority = substr($authority, 0, $colon);
        }

        return strtolower($authority);
    }

    /**
     * Splits text into ordered segments for the renderer to build nodes from.
     * Each is ['type' => 'text'|'url'|'hashtag'|'mention', 'text' => shown text,
     * and for a hashtag 'tag' => the lowercased tag, or for a mention
     * 'username' => the lowercased username]. Adjacent text segments are
     * merged so a run's formatting wraps one node per contiguous stretch
     * (matching today's output for URL/hashtag/mention-free text exactly).
     *
     * @return array<int, array{type: string, text: string, tag?: string}>
     */
    public static function tokenize(string $text): array
    {
        $segments = [];
        $cursor = 0;

        if (preg_match_all('{' . self::SCAN . '}', $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                [$matched, $offset] = $match[0];
                $classified = self::classify($matched);

                if ($classified === null) {
                    // Not actually a link (e.g. #2024 has no letter) - leave it
                    // in the text by not advancing the cursor past it.
                    continue;
                }

                if ($offset > $cursor) {
                    $segments[] = ['type' => 'text', 'text' => substr($text, $cursor, $offset - $cursor)];
                }

                $segments[] = $classified['segment'];
                $cursor = $offset + strlen($matched);

                if ($classified['trailing'] !== '') {
                    $segments[] = ['type' => 'text', 'text' => $classified['trailing']];
                }
            }
        }

        if ($cursor < strlen($text)) {
            $segments[] = ['type' => 'text', 'text' => substr($text, $cursor)];
        }

        return self::mergeText($segments);
    }

    /**
     * Whether a string is usable as a tag: made only of tag characters, no
     * longer than the cap in characters (not bytes - a tag of CJK is as long
     * as it looks), and not just a number, so "#2024" stays text.
     *
     * The one place that decides it, so a tag the renderer links and a tag
     * /tags/ will serve are the same thing by construction.
     */
    public static function isTagSlug(string $tag): bool
    {
        if ($tag === '' || mb_strlen($tag) > self::MAX_TAG_LENGTH) {
            return false;
        }

        if (preg_match('{^' . self::TAG_CHARS . '+$}', $tag) !== 1) {
            return false;
        }

        return trim($tag, '0123456789_') !== '';
    }

    /**
     * @return array{segment: array{type: string, text: string, tag?: string}, trailing: string}|null
     */
    private static function classify(string $matched): ?array
    {
        if ($matched[0] === '#') {
            $tag = substr($matched, 1);

            if (!self::isTagSlug($tag)) {
                return null;
            }

            // mb_strtolower, not strtolower: the ASCII-only one leaves #CAFÉ
            // as "cafÉ" while the browser's toLowerCase gives "café", and the
            // two renderers would link one tag to two different pages.
            return ['segment' => ['type' => 'hashtag', 'text' => $matched, 'tag' => mb_strtolower($tag)], 'trailing' => ''];
        }

        if ($matched[0] === '@') {
            $username = substr($matched, 1);

            if ($username === '' || strlen($username) > self::MAX_MENTION_LENGTH) {
                return null;
            }

            // Lowercased for both display and the link - unlike a hashtag
            // (an arbitrary, casing-optional user-chosen tag), a username is
            // always stored lowercase (signup.php/main.js's signup form both
            // enforce it), so there's no legitimate original casing to keep.
            $lowercased = strtolower($username);

            return ['segment' => ['type' => 'mention', 'text' => '@' . $lowercased, 'username' => $lowercased], 'trailing' => ''];
        }

        $url = rtrim($matched, self::URL_TRAILING_TRIM);
        $trailing = substr($matched, strlen($url));

        $lower = strtolower($url);
        $scheme_length = str_starts_with($lower, 'https://') ? 8 : (str_starts_with($lower, 'http://') ? 7 : 0);

        // Trimmed down to just the scheme (e.g. "https://).") - not a real URL.
        if ($scheme_length > 0 && strlen($url) === $scheme_length) {
            return null;
        }

        // The text stays as it was written; where the scheme is missing the
        // destination supplies one, since a link has to be absolute to lead
        // anywhere and https is what a bare host means now.
        return [
            'segment' => [
                'type' => 'url',
                'text' => $url,
                'href' => $scheme_length > 0 ? $url : 'https://' . $url,
            ],
            'trailing' => $trailing,
        ];
    }

    /**
     * @param array<int, array{type: string, text: string, tag?: string}> $segments
     * @return array<int, array{type: string, text: string, tag?: string}>
     */
    private static function mergeText(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            $last = count($merged) - 1;

            if ($segment['type'] === 'text' && $last >= 0 && $merged[$last]['type'] === 'text') {
                $merged[$last]['text'] .= $segment['text'];

                continue;
            }

            $merged[] = $segment;
        }

        return $merged;
    }
}
