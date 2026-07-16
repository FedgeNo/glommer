<?php

declare(strict_types=1);

/**
 * The shared link/hashtag logic for the render pass, kept byte-for-byte in step
 * with the JS mirror in delta.js (the two renderers must produce identical DOM).
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
class Linkify
{
    // Longest tag we linkify / store. Enforced in code, not the regex, so the
    // pattern body stays free of {} and can share one delimiter with JS.
    public const MAX_TAG_LENGTH = 50;

    // Longest @mention username we linkify. Independent of MAX_TAG_LENGTH even
    // though it happens to match Users.username's varchar(50) - a coincidence,
    // not a shared concept.
    public const MAX_MENTION_LENGTH = 50;

    // Trailing chars trimmed off a matched URL back into following text, so a
    // sentence's "...at https://x.com." doesn't swallow the period (or a wrapping
    // ")"). A URL that legitimately ends in one of these loses it - accepted.
    private const URL_TRAILING_TRIM = '.,!?;:)';

    // The pass-2 scanner: an http(s) URL, OR a #hashtag preceded by a boundary
    // (not a word char or another #, so a#b and ##b don't tag), OR an @mention
    // preceded by a boundary (not a word char or another @, so user@host isn't
    // mistaken for a mention). URL first so a '#'/'@' inside a URL (its
    // character class already allows '@', e.g. userinfo@host) never starts a
    // hashtag/mention of its own. Shared verbatim with delta.js via the same
    // string; only the delimiter differs (PHP {} vs JS new RegExp). No {} in
    // the body so the {} delimiter is safe.
    private const SCAN = "https?://[A-Za-z0-9._~:/?#\\[\\]@!$&'()*+,;=%-]+|(?<![A-Za-z0-9_#])#[A-Za-z0-9_]+|(?<![A-Za-z0-9_@])@[A-Za-z0-9_]+";

    // Pass-1 detector: an http(s) URL, a www.-prefixed host, or a bare
    // domain.tld/ (with a path slash) - the shapes a human reads as a link.
    private const LOOKS_URL = 'https?://|www\\.[A-Za-z0-9-]|[A-Za-z0-9-]+\\.[A-Za-z][A-Za-z]+/';

    // Extracts a URL's authority (userinfo@host:port). Shared with delta.js so
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
     * @return array{segment: array{type: string, text: string, tag?: string}, trailing: string}|null
     */
    private static function classify(string $matched): ?array
    {
        if ($matched[0] === '#') {
            $tag = substr($matched, 1);

            if ($tag === '' || strlen($tag) > self::MAX_TAG_LENGTH || preg_match('{[A-Za-z]}', $tag) !== 1) {
                return null;
            }

            return ['segment' => ['type' => 'hashtag', 'text' => $matched, 'tag' => strtolower($tag)], 'trailing' => ''];
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

        // Trimmed down to just the scheme (e.g. "https://).") - not a real URL.
        if (preg_match('{^https?://.}', $url) !== 1) {
            return null;
        }

        return ['segment' => ['type' => 'url', 'text' => $url], 'trailing' => $trailing];
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
