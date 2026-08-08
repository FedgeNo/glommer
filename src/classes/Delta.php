<?php

declare(strict_types=1);

/**
 * Helpers for the Quill Delta a post's rich text is stored as: decoding the
 * stored/submitted JSON to an ops array, sanitizing user-submitted ops down to
 * the formats this app actually renders (DeltaRenderer.php / DeltaRenderer.js), and
 * deriving the flat plaintext that Posts.description now holds (the "document"
 * form used for the <meta>/OG description, the RSS summary, and the FULLTEXT
 * search index). The ops array is the one shape both renderers consume.
 */
class Delta
{
    // Inline text attributes kept on a string insert; anything else is dropped.
    private const INLINE_FLAGS = ['bold', 'italic', 'underline', 'strike', 'code'];

    // Block attributes kept on a line-ending insert.
    private const VALID_HEADERS = [1, 2, 3];
    private const VALID_LISTS = ['ordered', 'bullet'];

    // Link schemes a stored link attribute may use (mirrors
    // DeltaRenderer::ALLOWED_LINK_SCHEMES); anything else is dropped here so an
    // unsafe scheme never reaches storage, let alone a renderer.
    private const SAFE_LINK_SCHEMES = ['http', 'https', 'mailto'];

    // A single formula's LaTeX source is capped here: a pathologically long one
    // is a KaTeX DoS and bloats the derived plaintext/alt text. No real formula
    // comes anywhere near this.
    private const MAX_FORMULA_LENGTH = 4096;

    /**
     * Decodes stored/submitted Delta JSON to its ops array. Accepts both a bare
     * `{"ops":[...]}` (what Quill's getContents() serializes) and a raw ops
     * array; anything malformed yields [].
     *
     * @return array[]
     */
    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (is_array($decoded) && isset($decoded['ops']) && is_array($decoded['ops'])) {
            return $decoded['ops'];
        }

        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * Reduces submitted ops to just the inserts and attributes this app renders,
     * so nothing outside that vocabulary is ever stored or handed to a renderer.
     * Each surviving op is either a string insert (with a filtered attributes
     * map) or a `{formula: string}` embed.
     *
     * @param array[] $ops
     * @return array[]
     */
    public static function sanitize(array $ops): array
    {
        $clean = [];

        foreach ($ops as $op) {
            if (!is_array($op) || !array_key_exists('insert', $op)) {
                continue;
            }

            $insert = $op['insert'];

            if (is_string($insert)) {
                $attributes = self::sanitizeAttributes($op['attributes'] ?? null);
                $clean_op = ['insert' => ControlCharacters::strip($insert)];

                if ($attributes !== []) {
                    $clean_op['attributes'] = $attributes;
                }

                $clean[] = $clean_op;
            } elseif (is_array($insert) && isset($insert['formula']) && is_string($insert['formula'])) {
                $clean[] = ['insert' => ['formula' => ControlCharacters::strip(mb_substr($insert['formula'], 0, self::MAX_FORMULA_LENGTH))]];
            }
        }

        return self::merged($clean);
    }

    /**
     * Consecutive runs that read the same way, folded into one.
     *
     * A delta is canonically compact - Quill's own never carries two adjacent
     * runs with identical formatting - but one built from markup does: a link
     * whose address is split across three spans arrives as three runs of the
     * same link. Rendered they are indistinguishable, so nothing downstream
     * noticed; written back out as markdown they are three links where the
     * author wrote one.
     *
     * @param array[] $ops
     * @return array[]
     */
    private static function merged(array $ops): array
    {
        $merged = [];

        foreach ($ops as $op) {
            $last = count($merged) - 1;

            if (
                $last >= 0
                && is_string($op['insert'])
                && is_string($merged[$last]['insert'])
                && ($merged[$last]['attributes'] ?? []) === ($op['attributes'] ?? [])
            ) {
                $merged[$last]['insert'] .= $op['insert'];

                continue;
            }

            $merged[] = $op;
        }

        return $merged;
    }

    /**
     * @return array<string, mixed> only the attributes DeltaRenderer honours
     */
    private static function sanitizeAttributes(mixed $attributes): array
    {
        if (!is_array($attributes)) {
            return [];
        }

        $clean = [];

        foreach (self::INLINE_FLAGS as $flag) {
            if (!empty($attributes[$flag])) {
                $clean[$flag] = true;
            }
        }

        if (isset($attributes['link']) && is_string($attributes['link']) && self::isSafeLink($attributes['link'])) {
            $clean['link'] = $attributes['link'];
        }

        // A client can send the header level as a JSON string ("2"); coerce it
        // to an int so DeltaRenderer's strict === test recognises it and the
        // server and client renders agree.
        if (isset($attributes['header']) && is_numeric($attributes['header']) && in_array((int) $attributes['header'], self::VALID_HEADERS, true)) {
            $clean['header'] = (int) $attributes['header'];
        }

        if (isset($attributes['list']) && in_array($attributes['list'], self::VALID_LISTS, true)) {
            $clean['list'] = $attributes['list'];
        }

        if (!empty($attributes['blockquote'])) {
            $clean['blockquote'] = true;
        }

        if (!empty($attributes['code-block'])) {
            $clean['code-block'] = true;
        }

        return $clean;
    }

    /**
     * Whether a link is safe to store (known scheme, or relative/scheme-relative).
     * Mirrors DeltaRenderer::isSafeLink(): strips ASCII control chars first, since
     * browsers do too when parsing a URL, so "java\tscript:" can't slip through.
     */
    private static function isSafeLink(string $url): bool
    {
        $stripped = preg_replace('/[\x00-\x20]+/', '', $url);

        if (!preg_match('/^([a-z][a-z0-9+.\-]*):/i', $stripped, $match)) {
            return true;
        }

        return in_array(strtolower($match[1]), self::SAFE_LINK_SCHEMES, true);
    }

    /**
     * The flat plaintext of a delta: every text insert plus each formula's LaTeX
     * source (kept so math stays searchable), with runs of whitespace collapsed
     * to single spaces. This is what Posts.description stores.
     *
     * @param array[] $ops
     */
    public static function plainText(array $ops): string
    {
        $text = '';

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;

            if (is_string($insert)) {
                $text .= $insert;
            } elseif (is_array($insert) && isset($insert['formula']) && is_string($insert['formula'])) {
                $text .= ' ' . $insert['formula'] . ' ';
            }
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * The same text laid out in paragraphs, one per block, separated by a
     * blank line - spaces and tabs collapsed inside a paragraph, empty blocks
     * dropped.
     *
     * Every newline in a delta ends a block: that is how DeltaRenderer reads
     * one, so a paragraph here is exactly what becomes a <p> there. Written
     * out as a blank line because that is what says "new paragraph" in plain
     * text, to a reader and to a model alike - a single newline would be read
     * back as a soft break and put the whole post in one paragraph.
     *
     * plainText() above flattens all of it to one line, which is right for a
     * search index and a meta description and wrong for anything that has to
     * read the way the post did.
     *
     * @param array[] $ops
     */
    public static function plainTextInParagraphs(array $ops): string
    {
        return implode(chr(10) . chr(10), self::paragraphs($ops));
    }

    /**
     * The same paragraphs as a list, for anything that wants one of them
     * rather than the whole body - a post with no title of its own is headed
     * by its opening line, which is only findable while the blocks are still
     * apart.
     *
     * @param array[] $ops
     * @return string[]
     */
    public static function paragraphs(array $ops): array
    {
        $text = '';

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;

            if (is_string($insert)) {
                $text .= $insert;
            } elseif (is_array($insert) && isset($insert['formula']) && is_string($insert['formula'])) {
                $text .= ' ' . $insert['formula'] . ' ';
            }
        }

        $paragraphs = [];

        foreach (explode(chr(10), str_replace(chr(13), '', $text)) as $block) {
            $words = array_filter(
                explode(' ', str_replace(chr(9), ' ', $block)),
                static fn (string $word): bool => $word !== ''
            );

            $block = implode(' ', $words);

            if ($block !== '') {
                $paragraphs[] = $block;
            }
        }

        return $paragraphs;
    }

    /**
     * @param array[] $ops
     */
    public static function isBlank(array $ops): bool
    {
        return self::plainText($ops) === '';
    }

    /**
     * The distinct #hashtags in a post's body (lowercased, first-seen order).
     * Uses the same rule the renderer linkifies with (Linkifier), and - matching
     * the renderer - skips runs that aren't linkified: inline code and text
     * already inside a link. Uncapped here; Hashtag::indexPost stores only a
     * post's first MAX_HASHTAGS of them.
     *
     * @param array[] $ops
     * @return string[]
     */
    public static function hashtags(array $ops): array
    {
        $tags = [];

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;

            if (!is_string($insert)) {
                continue;
            }

            $attributes = $op['attributes'] ?? [];

            if (!empty($attributes['code']) || isset($attributes['link'])) {
                continue;
            }

            foreach (Linkifier::tokenize($insert) as $segment) {
                if ($segment['type'] === 'hashtag') {
                    $tags[$segment['tag']] = true;
                }
            }
        }

        return array_keys($tags);
    }

    /**
     * The distinct @mentioned usernames in a post's body (lowercased -
     * Linkifier::tokenize() already lowercases them, same as usernames
     * themselves always are - first-seen order. Same skip rules as
     * hashtags() (inline code, already-linked text). Uncapped; Mention::indexPost
     * applies the spam policy and resolves which usernames are real users.
     *
     * @param array[] $ops
     * @return string[]
     */
    public static function mentions(array $ops): array
    {
        $usernames = [];

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;

            if (!is_string($insert)) {
                continue;
            }

            $attributes = $op['attributes'] ?? [];

            if (!empty($attributes['code']) || isset($attributes['link'])) {
                continue;
            }

            foreach (Linkifier::tokenize($insert) as $segment) {
                if ($segment['type'] === 'mention') {
                    $usernames[$segment['username']] = true;
                }
            }
        }

        return array_keys($usernames);
    }
}
