<?php

declare(strict_types=1);

/**
 * The words the software says, in whichever language it is saying them.
 *
 * A PHP array file per locale under src/locales/, because there is no build
 * step here and there is not going to be one: an array file is compiled once
 * by opcache and read back as a hash lookup, where JSON would be parsed on
 * every request and a database table would be a query on every page.
 *
 * Everything a person reads comes from here, including the label on a button.
 * A class keeps only the shape of a sentence - which piece is a link, where it
 * points - so "is this class translated" has an answer anybody can check: it
 * contains no English.
 *
 * Missing keys fall back to English rather than to a blank or a key name. That
 * is what makes this possible to adopt at all: over a thousand strings cannot
 * be converted in one change, and every intermediate state has to be a site
 * somebody can use.
 */
class Strings
{
    /** The locale every other one falls back to, and the one the code is written in. */
    public const SOURCE_LOCALE = 'en';

    private const DIRECTORY = __DIR__ . '/../locales';

    private static ?string $locale = null;

    /** @var array<string, array<string, mixed>> loaded tables by locale */
    private static array $tables = [];

    /**
     * The locales this installation has words for, source first.
     *
     * Read off the directory rather than listed anywhere: adding a translation
     * is adding a file, and a list would be a second place to remember.
     *
     * @return string[]
     */
    public static function available(): array
    {
        $found = [];

        foreach ((array) glob(self::DIRECTORY . '/*.php') as $path) {
            $found[] = basename((string) $path, '.php');
        }

        sort($found);

        return $found;
    }

    /**
     * Which language to say it in.
     *
     * The browser's Accept-Language, matched against what this installation
     * has, and English otherwise. A per-member setting belongs in front of this
     * once there is a column for it - somebody who has said what they want
     * should not be re-asked by every browser they open.
     */
    public static function locale(): string
    {
        if (self::$locale !== null) {
            return self::$locale;
        }

        $available = self::available();

        foreach (self::preferredLanguages() as $language) {
            // A tag names a language and optionally a place - de-AT is German.
            // The place only narrows it, so the language alone is a match.
            $base = strtolower(explode('-', $language)[0]);

            if (in_array($base, $available, true)) {
                return self::$locale = $base;
            }
        }

        return self::$locale = self::SOURCE_LOCALE;
    }

    /** Set the locale directly, for the times nothing is asking a browser. */
    public static function useLocale(?string $locale): void
    {
        self::$locale = $locale !== null && in_array($locale, self::available(), true) ? $locale : null;
    }

    /**
     * What one class says, in the current locale, with anything untranslated
     * falling back to the English it was written in.
     *
     * @return array<string, mixed>
     */
    public static function for(string $class): array
    {
        $words = self::table(self::locale())[$class] ?? [];
        $source = self::table(self::SOURCE_LOCALE)[$class] ?? [];

        // Recursive, so a locale that translates one phrasing of a sentence
        // and not another keeps English for the one it missed rather than
        // losing it along with the rest of the entry.
        return array_replace_recursive($source, $words);
    }

    /**
     * The whole table, for handing to the browser - see locales.php, which
     * serves it as the module the client twins read.
     *
     * @return array<string, mixed>
     */
    public static function all(?string $locale = null): array
    {
        $locale ??= self::locale();

        return array_replace_recursive(self::table(self::SOURCE_LOCALE), self::table($locale));
    }

    /** @return array<string, mixed> */
    private static function table(string $locale): array
    {
        if (isset(self::$tables[$locale])) {
            return self::$tables[$locale];
        }

        // Checked against the directory listing rather than pattern-matched:
        // this ends up in a require, and the only safe answer to "which file"
        // is one of the files that is actually there.
        if (!in_array($locale, self::available(), true)) {
            return self::$tables[$locale] = [];
        }

        $table = require self::DIRECTORY . '/' . $locale . '.php';

        return self::$tables[$locale] = is_array($table) ? $table : [];
    }

    /**
     * The languages the browser asked for, best first.
     *
     * @return string[]
     */
    private static function preferredLanguages(): array
    {
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

        if (trim($header) === '') {
            return [];
        }

        $languages = [];

        foreach (explode(',', $header) as $part) {
            $pieces = explode(';q=', trim($part));
            $tag = trim($pieces[0]);

            if ($tag === '' || $tag === '*') {
                continue;
            }

            $languages[$tag] = (float) ($pieces[1] ?? 1.0);
        }

        arsort($languages);

        return array_keys($languages);
    }
}
