<?php

declare(strict_types=1);

/**
 * What each language calls itself.
 *
 * Not in the locale files, and deliberately: a language's own name is the same
 * string whichever language is asking. Deutsch is Deutsch on the English
 * settings page and on the Japanese one, and putting it in every locale file
 * would be nine copies of one fact - nine chances for a language to be listed
 * under a name its own speakers do not use.
 *
 * Written the way each is written by the people who speak it, in its own
 * script, so somebody who cannot read the rest of the page can still find
 * their language in the list.
 */
class LanguageName
{
    /** @var array<string, string> locale => what its speakers call it */
    private const NAMES = [
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
        'ja' => '日本語',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'pt' => 'Português',
        'pt-BR' => 'Português (Brasil)',
        'zh-Hant' => '繁體中文',
        'fil' => 'Filipino',
    ];

    public static function of(string $locale): string
    {
        return self::NAMES[$locale] ?? self::endonym($locale) ?? $locale;
    }

    /**
     * What ICU says this language calls itself, for a language the list above
     * has not met - so adding a locale file lists it by its name rather than
     * by its code until somebody writes it in.
     *
     * Title-cased because this is a list entry: French writes "français" in a
     * sentence and "Français" at the head of a line, and every name in the
     * list above leads with a capital.
     */
    private static function endonym(string $locale): ?string
    {
        $named = \Locale::getDisplayLanguage($locale, $locale);

        if (!is_string($named) || $named === '' || $named === $locale) {
            return null;
        }

        return mb_ucfirst($named);
    }

    /**
     * Every language this installation has words for, in its own name.
     *
     * Ordered by that name rather than by locale code, since the name is what
     * somebody is reading down the list looking for.
     *
     * @return array<string, string> locale => name
     */
    public static function all(): array
    {
        $named = [];

        foreach (Strings::available() as $locale) {
            $named[$locale] = self::of($locale);
        }

        asort($named, SORT_NATURAL | SORT_FLAG_CASE);

        return $named;
    }
}
