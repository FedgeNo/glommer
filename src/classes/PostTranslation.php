<?php

declare(strict_types=1);

/**
 * A post's body in another language, translated on a reader's explicit
 * request and cached per (post, language) - the model is asked once, every
 * later reader of that pairing gets the stored answer.
 *
 * The language is a caller-supplied string (a BCP 47 tag today), kept
 * deliberately opaque: when the interface itself learns other languages,
 * whatever identifies them keeps working here unchanged.
 */
class PostTranslation
{
    /**
     * Longer than this and a free-tier model starts truncating mid-thought;
     * a reader is better served by no translation than by half of one.
     */
    public const MAX_SOURCE_LENGTH = 4000;

    /**
     * The variants worth translating separately, by base language: the ones
     * where a reader gets a materially different text, not merely a different
     * spelling of the same one.
     *
     * Everything absent from here reduces to its base language. A browser
     * reports "en-US", "en-GB", "en-CA" and a dozen more, and treating those
     * as different languages would ask a model for the same English over and
     * over and store each answer under its own key - a cache that misses
     * almost every time it is asked.
     */
    private const VARIANTS = [
        'pt' => ['br' => 'br', 'pt' => 'pt'],
        'zh' => ['hans' => 'hans', 'hant' => 'hant', 'cn' => 'hans', 'sg' => 'hans', 'tw' => 'hant', 'hk' => 'hant', 'mo' => 'hant'],
        'sr' => ['cyrl' => 'cyrl', 'latn' => 'latn'],
    ];

    /**
     * A usable language identifier, or null. BCP 47 shapes ("en", "pt-BR",
     * "zh-hant") normalize to lowercase and down to the language actually
     * being asked for; anything else is refused rather than passed to a model
     * as free text.
     *
     * This is the cache key as well as what the prompt names, so two readers
     * asking for the same language in different words are one model call and
     * one stored answer.
     */
    public static function normalizeLanguage(string $language): ?string
    {
        $language = strtolower(trim($language));

        if (preg_match('/\A[a-z0-9]{2,8}(-[a-z0-9]{1,8})*\z/', $language) !== 1 || strlen($language) > 35) {
            return null;
        }

        $subtags = explode('-', $language);
        $base = $subtags[0];
        $variants = self::VARIANTS[$base] ?? [];

        foreach (array_slice($subtags, 1) as $subtag) {
            if (isset($variants[$subtag])) {
                return $base . '-' . $variants[$subtag];
            }
        }

        return $base;
    }

    public static function cached(int $post_id, string $language): ?string
    {
        $row = DB::row('
SELECT `body`
    FROM `PostTranslations`
    WHERE `postId` = ? AND `language` = ?
', \stdClass::class, 'is', $post_id, $language);

        return $row ?-> body;
    }

    /**
     * Translates and stores, or returns null where it could not be done - no
     * translation environment, no package for that pairing, nothing readable
     * back.
     *
     * Done on this machine (see Translator). It used to be a free LLM router,
     * which answered the same request in 1.5 seconds and then in 16.9 because
     * it picks a different model every call - a reader pressing Translate had
     * no idea whether to wait a moment or give up. It also sent what somebody
     * wrote to a third party to do it.
     */
    /**
     * What language a post is in, as well as this server knows.
     *
     * The extractor works it out off the words, which is the only reliable
     * way. The declared language stands in until then - it is what the
     * sender's account setting says rather than what they wrote, so it is a
     * poor answer and only used where there is no better one.
     */
    private static function sourceLanguage(int $post_id): ?string
    {
        $post = DB::row('
SELECT `detectedLanguage`, `language`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        return $post ?-> detectedLanguage ?? $post ?-> language;
    }

    /** Why this post cannot be translated into that language, or null if it can. */
    public static function refusalFor(int $post_id, string $language, string $body): ?string
    {
        return Translator::refusalFor($body, $language, self::sourceLanguage($post_id));
    }

    /** What to tell a reader about a refusal. */
    public static function refusalMessage(string $refusal): string
    {
        $words = Strings::for(self::class);

        return (string) ($words[$refusal] ?? $words[Translator::UNAVAILABLE] ?? '');
    }

    public static function translate(int $post_id, string $language, string $body): ?string
    {
        // A translator has to be told what it is translating from, and this
        // server has already worked that out for anything the entity
        // extractor has read - off the words, which is the only reliable way.
        //
        // The declared language stands in until then, for the window between a
        // post arriving and the next trending pass. It is what a sender's
        // account setting says rather than what they wrote, so it is a poor
        // answer and only used where there is no better one; nothing is gated
        // on it, and being wrong costs one bad translation the reader asked
        // for rather than something silently withheld.
        $translated = Translator::translate($body, $language, self::sourceLanguage($post_id));

        if ($translated === null) {
            return null;
        }

        $translated = trim(ControlCharacters::strip($translated));

        if ($translated === '' || strlen($translated) > 65535) {
            return null;
        }

        DB::run('
INSERT INTO `PostTranslations` (`postId`, `language`, `body`)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE `body` = VALUES(`body`)
', 'iss', $post_id, $language, $translated);

        return $translated;
    }
}
