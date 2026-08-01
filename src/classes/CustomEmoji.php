<?php

declare(strict_types=1);

/**
 * Custom emoji - a :shortcode: that means a picture rather than a character.
 *
 * The unrelated thing sharing that syntax is the Unicode shortcode table
 * (EmojiShortcodeMap), which is a fixed vocabulary everyone can resolve. A
 * custom emoji is the opposite: every server has its own set, nobody can
 * resolve a name they were not told about, and so a post carries the meaning
 * with it as an Emoji tag beside its content.
 *
 * Which is why these are keyed by the server they came from. :blobcat: on one
 * instance and :blobcat: on another are different pictures, and both have to be
 * storable at once - a single global table would make whichever arrived first
 * silently stand in for the other.
 *
 * Learned rather than curated: a tag on a post is a sender stating what a name
 * in its own text means, and that is the only authority there is for it.
 */
class CustomEmoji
{
    /** Matches the shortcode column, and far above any real name. */
    private const MAX_SHORTCODE_LENGTH = 64;

    /**
     * Records what a post's Emoji tags say, and returns the map that post
     * should be rendered with.
     *
     * Scoped to the sending server: a tag can only ever teach this server about
     * names on its own instance, never overwrite another's.
     *
     * @param array<int, mixed> $tags the object's tag array, as received
     * @return array<string, string> shortcode without colons => image URL
     */
    public static function learnFrom(array $tags, string $object_uri): array
    {
        $domain = self::domainOf($object_uri);

        if ($domain === null) {
            return [];
        }

        $learned = [];

        foreach ($tags as $tag) {
            if (!is_array($tag) || ($tag['type'] ?? null) !== 'Emoji') {
                continue;
            }

            $shortcode = self::normalizeName($tag['name'] ?? null);
            $image = $tag['icon']['url'] ?? null;

            if ($shortcode === null || !is_string($image) || $image === '' || strlen($image) > 255) {
                continue;
            }

            // Only ever an https image. A tag is content from another server,
            // and this URL ends up in an img on our page.
            if (strtolower((string) parse_url($image, PHP_URL_SCHEME)) !== 'https') {
                continue;
            }

            DB::run('
INSERT INTO `CustomEmojis` (`domain`, `shortcode`, `imageURL`)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE `imageURL` = VALUES(`imageURL`)
', 'sss', $domain, $shortcode, $image);

            $learned[$shortcode] = $image;
        }

        return $learned;
    }

    /**
     * Every custom emoji a server has taught us, for rendering a post of theirs
     * that was stored before its tags were read - or one whose tags named an
     * emoji the sender had already introduced elsewhere.
     *
     * @return array<string, string>
     */
    public static function forObject(?string $object_uri): array
    {
        $domain = $object_uri === null ? null : self::domainOf($object_uri);

        if ($domain === null) {
            return [];
        }

        $rows = DB::rows('
SELECT `shortcode`, `imageURL`
    FROM `CustomEmojis`
    WHERE `domain` = ?
', 'CustomEmojiData', 's', $domain);

        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row -> shortcode] = (string) $row -> imageURL;
        }

        return $map;
    }

    /**
     * A tag's name as it is stored: the colons stripped and lowercased, since a
     * shortcode is matched without regard to case the same way the Unicode ones
     * are.
     */
    private static function normalizeName(mixed $name): ?string
    {
        if (!is_string($name)) {
            return null;
        }

        $name = strtolower(trim($name, ": \t\n\r\0\x0B"));

        // The same character set the matcher recognises. A name outside it
        // could never be typed back, so storing it would fill the table with
        // entries nothing can reach.
        if ($name === '' || strlen($name) > self::MAX_SHORTCODE_LENGTH || preg_match('/\A[a-z0-9_+-]+\z/', $name) !== 1) {
            return null;
        }

        return $name;
    }

    /** The server an object belongs to, which is what scopes its emoji. */
    private static function domainOf(string $object_uri): ?string
    {
        $host = parse_url($object_uri, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
