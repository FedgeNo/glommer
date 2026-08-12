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
     * The key a locale file puts its counting rule under. Not a class name, so
     * it cannot collide with one, and skipped when a table is read as words.
     */
    public const PLURAL_RULE = '@plural';

    /**
     * One of a set of phrasings, chosen by how many of the thing there are.
     *
     * English has two forms and most of the code was written assuming that -
     * `$n === 1 ? 'vote' : 'votes'`. Polish has three and Arabic six, and no
     * amount of care with a ternary in a class will produce them; the choosing
     * has to belong to the language.
     *
     * So a counted string is a set of phrasings keyed by CLDR category, and
     * each locale file says which category a number falls in. A locale that
     * gives only "other" is a language with one form, which is a real answer
     * and what Japanese wants.
     *
     * The count is not substituted here: the phrasing says where the number
     * goes, or leaves it out, which is a thing languages differ about too.
     */
    public static function plural(string $class, string $key, int $count): string
    {
        $forms = self::for($class)[$key] ?? [];

        if (!is_array($forms) || $forms === []) {
            return '';
        }

        $category = self::category($count);

        // Falling back to "other" rather than to nothing: a locale that has
        // not written its "few" yet should read a little wrong, not vanish.
        $chosen = $forms[$category] ?? $forms['other'] ?? reset($forms);

        return is_string($chosen) ? $chosen : '';
    }

    /** Which CLDR category a count falls in, by the current locale's own rule. */
    private static function category(int $count): string
    {
        $rule = self::table(self::locale())[self::PLURAL_RULE]
            ?? self::table(self::SOURCE_LOCALE)[self::PLURAL_RULE]
            ?? null;

        if (!is_callable($rule)) {
            // What English does, which is what the code assumed before any of
            // this and is right for a good half of the languages there are.
            return $count === 1 ? 'one' : 'other';
        }

        return (string) $rule($count);
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
     * The whole table, for handing to the browser - the locales/*.js modules
     * the client twins read are this, written out.
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
        $table = is_array($table) ? $table : [];

        // Plus anything in a directory of the same name, merged in. A locale
        // is a thousand strings before long, and one file is both an awkward
        // thing to read and the file every hand touches at once - a fragment
        // per area of the site keeps a change to the messaging strings out of
        // the way of a change to the settings ones.
        foreach ((array) glob(self::DIRECTORY . '/' . $locale . '/*.php') as $fragment) {
            $part = require (string) $fragment;

            if (is_array($part)) {
                $table = array_replace_recursive($table, $part);
            }
        }

        return self::$tables[$locale] = $table;
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
