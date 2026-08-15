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
     * The counts worth asking about: where CLDR rules change their minds - the
     * teens and the hundreds for Slavic, and the thousands where a language's
     * rules turn on the last two digits.
     */
    private const RANGE = [
        0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
        21, 22, 23, 24, 25, 30, 31, 40, 41, 50, 60, 70, 80, 90,
        100, 101, 102, 105, 111, 112, 120, 121, 130,
        200, 201, 1000, 1002, 1005, 1011, 1024,
    ];

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
        static $categories = [];

        if (isset($categories[$locale])) {
            return $categories[$locale];
        }

        $found = [];

        foreach (self::RANGE as $count) {
            $found[self::categoryFor($locale, $count)] = true;
        }

        return $categories[$locale] = array_values(array_filter(
            self::CATEGORIES,
            static fn (string $category): bool => isset($found[$category])
        ));
    }

    /**
     * A count that falls in this category, for asking a question about it.
     *
     * A model handed "{count} views" with the number masked out has no sentence
     * to work with and answers the mask back, and one handed the same English
     * for every category answers the same phrasing for every category - which
     * is how Polish would end up with one word where it counts in three. Asked
     * "5 views" it declines the noun the way five of them wants.
     *
     * One is preferred where the category has it, because a category holding 1
     * is the singular whatever else it holds, and Portuguese counting 0 as a
     * singular would otherwise be asked about none of them.
     */
    public static function exampleFor(string $locale, string $category): ?int
    {
        $counts = [];

        foreach (self::RANGE as $count) {
            if (self::categoryFor($locale, $count) === $category) {
                $counts[] = $count;
            }
        }

        if ($counts === []) {
            return null;
        }

        if (in_array(1, $counts, true)) {
            return 1;
        }

        $counting = array_filter($counts, static fn (int $count): bool => $count > 1);

        return $counting === [] ? $counts[0] : min($counting);
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
            $formatters[$locale] = self::known($locale) ? new \MessageFormatter($locale, self::BRANCHES) : null;
        } catch (\Throwable) {
            $formatters[$locale] = null;
        }

        return $formatters[$locale];
    }

    /**
     * Whether CLDR has rules for this language at all.
     *
     * Asked first, because an unrecognised tag is not refused: ICU answers it
     * from the root rules, which say "other" to every count - a language with
     * no singular, which is a real answer for Japanese and a wrong one for
     * anything else. Nothing is the better answer, because it is the one that
     * falls back to English's two forms rather than to none.
     */
    private static function known(string $locale): bool
    {
        static $languages = null;

        if ($languages === null) {
            $languages = [];

            foreach (\ResourceBundle::getLocales('') as $available) {
                $languages[\Locale::getPrimaryLanguage($available)] = true;
            }
        }

        return isset($languages[\Locale::getPrimaryLanguage($locale)]);
    }
}
