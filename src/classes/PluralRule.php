<?php

declare(strict_types=1);

/**
 * Which CLDR category a count falls in, for a language.
 *
 * How a language counts is a fact about that language rather than anything
 * about this site, and CLDR is where that fact is written down. ICU carries it
 * here and Intl.PluralRules carries the same data in the browser, so both
 * sides ask one source and a new language needs nothing written for it: Polish
 * gets its three forms and Arabic its six by being named, not by anybody
 * transcribing a rule into a file to be wrong in.
 *
 * A language with one form answers "other" to every count, which is a real
 * answer and what Japanese wants - and indistinguishable from what a code ICU
 * has never heard of answers, since that falls back to root. The browser falls
 * back elsewhere, so the two would quietly disagree; LocaleIntegrityTest keeps
 * every locale file to a name ICU knows rather than leaving that to notice.
 */
class PluralRule
{
    /**
     * Asked as a message with a branch per category, because selecting one is
     * the only thing ICU exposes the rules through - the answer is the name of
     * the branch it took.
     */
    private const BRANCHES = '{n, plural, zero{zero} one{one} two{two} few{few} many{many} other{other}}';

    /** The order CLDR names them in, so a widened phrase comes out stably. */
    public const CATEGORIES = ['zero', 'one', 'two', 'few', 'many', 'other'];

    /**
     * Every category this language actually counts in.
     *
     * Found by counting rather than by looking a list up, over a range that
     * covers every shape a CLDR rule turns on - the teens and the hundreds are
     * where Slavic and Arabic rules change their minds.
     *
     * @return string[]
     */
    public static function categoriesFor(string $locale): array
    {
        $found = [];

        foreach ([...range(0, 130), 200, 201, 1000, 1002, 1005, 1011, 1024] as $count) {
            $found[self::categoryFor($locale, $count)] = true;
        }

        return array_values(array_filter(
            self::CATEGORIES,
            static fn (string $category): bool => isset($found[$category])
        ));
    }

    public static function categoryFor(string $locale, int $count): string
    {
        $formatter = self::formatterFor($locale);

        if ($formatter === null) {
            // What English does, which is right for a good half of the
            // languages there are and quietly coarse rather than fatal for the
            // rest - see the intl note in the README's requirements.
            return $count === 1 ? 'one' : 'other';
        }

        $category = $formatter -> format(['n' => $count]);

        return is_string($category) && $category !== '' ? $category : 'other';
    }

    /** Kept per language: a page counts many things and builds one of these. */
    private static function formatterFor(string $locale): ?\MessageFormatter
    {
        static $formatters = [];

        if (array_key_exists($locale, $formatters)) {
            return $formatters[$locale];
        }

        try {
            $formatters[$locale] = new \MessageFormatter($locale, self::BRANCHES);
        } catch (\Throwable) {
            $formatters[$locale] = null;
        }

        return $formatters[$locale];
    }
}
