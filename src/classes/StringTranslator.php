<?php

declare(strict_types=1);

/**
 * Writes the interface's locale files by translating the English one.
 *
 * Only what has changed is translated. Each locale keeps, beside it, the
 * fingerprint of the English every one of its strings was made from, so a
 * second run with nothing edited translates nothing and a run after one edited
 * string translates one string.
 *
 * Nothing has to be complete. Strings falls back to English per key, so a
 * locale is written with the keys that translated and left without the ones
 * that did not, rather than being held back until all of it works.
 */
class StringTranslator
{
    /** Fingerprints of the English each locale was translated from. */
    private const SOURCES = __DIR__ . '/../../locales/sources';

    /** @var string[] */
    private array $only;

    private bool $force;

    /** @var string[] */
    private array $paths;

    /**
     * @param string[] $only Locales to do, or none for every installed one.
     * @param string[] $paths Keys to do, or none for whatever has gone stale.
     */
    public function __construct(array $only = [], bool $force = false, array $paths = [])
    {
        $this -> only = $only;
        $this -> force = $force;
        $this -> paths = $paths;
    }

    /**
     * Every translatable string in a locale table, by dotted path.
     *
     * Only strings: a locale carries numbers ("clock": 12) as well, and a
     * number is not language a model has anything to say about.
     *
     * $keep_empty is for reading a locale rather than the source. An empty
     * string is nothing to translate, but in a locale it is an answer - the
     * half of a split sentence this language has no words for - and telling
     * that apart from a key nobody has got to yet is the whole point.
     *
     * @param array<string, mixed> $table
     * @return array<string, string>
     */
    public static function flatten(array $table, string $prefix = '', bool $keep_empty = false): array
    {
        $flat = [];

        foreach ($table as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flat += self::flatten($value, $path, $keep_empty);
            } elseif (is_string($value) && ($keep_empty || trim($value) !== '')) {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    /**
     * The English to translate, with every counted phrase widened to the
     * categories this language counts in.
     *
     * English counts in two and the work list is English's, so without this
     * nothing ever asks for a form English does not have - and Polish could
     * never be given the "few" and "many" its grammar requires, however many
     * times the pass was run. The extra forms start from English's plural,
     * which is the only English there is for them; what a language does with
     * that is the translation's business.
     *
     * @param array<string, string> $source English, by path
     * @return array<string, string>
     */
    public static function expanded(array $source, string $locale): array
    {
        $groups = [];

        foreach ($source as $path => $english) {
            $at = strrpos($path, '.');

            if ($at !== false) {
                $groups[substr($path, 0, $at)][substr($path, $at + 1)] = $english;
            }
        }

        foreach ($groups as $parent => $forms) {
            // A counted phrase is one whose every key is a category, which no
            // sentence-of-pieces entry is - those are keyed before/link/after.
            if (array_diff(array_keys($forms), PluralRule::CATEGORIES) !== []) {
                continue;
            }

            $plural = $forms['other'] ?? $forms['one'] ?? null;

            if ($plural === null) {
                continue;
            }

            $wanted = PluralRule::categoriesFor($locale);

            foreach (PluralRule::CATEGORIES as $category) {
                if (in_array($category, $wanted, true)) {
                    $source[$parent . '.' . $category] ??= $plural;

                    continue;
                }

                // Not asked for what this language never selects: Japanese
                // counts one way, so English's "one" is a phrasing it can
                // never reach, and translating it fills the file with lines
                // no reader will see. "other" stays regardless, because it is
                // what Strings::plural falls back to.
                if ($category !== 'other') {
                    unset($source[$parent . '.' . $category]);
                }
            }
        }

        return $source;
    }

    /**
     * The counted phrasings among flattened paths, by the path of the set
     * itself: a set is an entry whose every key names a CLDR category.
     *
     * @param array<string, string> $source
     * @return array<string, array<string, string>>
     */
    public static function counted(array $source): array
    {
        $sets = [];

        foreach ($source as $path => $english) {
            $cut = strrpos($path, '.');

            if ($cut === false) {
                continue;
            }

            $sets[substr($path, 0, $cut)][substr($path, $cut + 1)] = $english;
        }

        foreach ($sets as $prefix => $forms) {
            if (array_diff(array_keys($forms), PluralRule::CATEGORIES) !== []) {
                unset($sets[$prefix]);
            }
        }

        return $sets;
    }

    /**
     * The English to ask a model about one form of a counted phrase, and the
     * number written into it.
     *
     * "{count} views" with its number masked is not a sentence: a model hands
     * the mask straight back, and every category of every language asked the
     * same question answers it the same way - which is how Polish would finish
     * with one word where it counts in three. Written out as "5 views", the
     * model has a sentence to decline, and the number comes back out
     * afterwards (recounted()).
     *
     * The number is one this language's own rule puts in that category, and
     * the English is English's own form for that count, so the question is a
     * real sentence rather than one assembled out of pieces.
     *
     * @param array<string, string> $forms English's phrasings for this entry
     * @return array{0: string, 1: int|null} what to ask, and the number in it
     */
    public static function sampled(string $locale, string $category, array $forms): array
    {
        $count = PluralRule::exampleFor($locale, $category);
        $english = $forms[$category] ?? $forms['other'] ?? reset($forms);

        if ($count === null || !is_string($english)) {
            return [is_string($english) ? $english : '', null];
        }

        $written = $forms[PluralRule::categoryFor(Strings::SOURCE_LOCALE, $count)]
            ?? $forms['other']
            ?? $english;

        return [str_replace('{count}', (string) $count, self::spelledOut($written)), $count];
    }

    /**
     * What to hand the model for one path, and the number standing in for
     * {count} where there is one.
     *
     * @param array<string, array<string, string>> $sets English's counted entries
     * @return array{0: string, 1: int|null}
     */
    public static function asked(string $locale, string $path, string $english, array $sets): array
    {
        $cut = strrpos($path, '.');
        $prefix = $cut === false ? '' : substr($path, 0, $cut);
        $category = $cut === false ? '' : substr($path, $cut + 1);

        if (!isset($sets[$prefix]) || !in_array($category, PluralRule::CATEGORIES, true)) {
            return [self::sourceFor($locale, $path, $english), null];
        }

        return self::sampled($locale, $category, $sets[$prefix]);
    }

    /**
     * A compact timestamp written out as the sentence it stands for, for
     * asking a model - the file keeps the compact form.
     *
     * "{count}m ago" is not translatable: measured, it came back as "five
     * years ago" in Arabic and Russian and "five metres ago" in Persian -
     * wrong rather than merely untranslated, and plausible in a script nobody
     * here can spot-check. "{count} minutes ago" answers correctly in every
     * language tested.
     */
    public static function spelledOut(string $english): string
    {
        $units = ['m' => 'minutes', 'h' => 'hours', 'd' => 'days', 'w' => 'weeks', 'y' => 'years'];

        if (preg_match('/^(\{count\}|[0-9]+)([mhdwy]) ago$/', $english, $found) === 1) {
            return $found[1] . ' ' . $units[$found[2]] . ' ago';
        }

        return $english;
    }

    /**
     * The number put back as the token it stood in for.
     *
     * Refused rather than guessed at when it did not survive exactly once: a
     * phrasing that has lost its number renders a sentence about no particular
     * quantity, and one that gained a second prints the count twice.
     */
    public static function recounted(string $translated, int $count, string $locale): ?string
    {
        foreach (self::numerals($count, $locale) as $numeral) {
            // A whole number, not a digit inside one: asked about 5, an
            // answer saying 15 does not contain the count - and rewriting
            // its 5 would store "1{count}" as though it were a translation.
            $whole = '/(?<!\p{Nd})' . preg_quote($numeral, '/') . '(?!\p{Nd})/u';

            if (preg_match_all($whole, $translated) === 1) {
                return (string) preg_replace($whole, '{count}', $translated);
            }
        }

        return null;
    }

    /**
     * The ways this number can be written, Western digits first.
     *
     * A model writing for Arabic, Persian or Bengali may answer in the digits
     * that language reads in, and a number that came back as ٥ is the number
     * asked about rather than a lost one.
     *
     * @return string[]
     */
    private static function numerals(int $count, string $locale): array
    {
        $numerals = [(string) $count];

        // The locale's own default, and its native digits as well: CLDR gives
        // Arabic Western digits by default, and a model writing Arabic answers
        // in the digits Arabic traditionally reads regardless.
        foreach ([$locale, $locale . '-u-nu-native'] as $tag) {
            try {
                $written = (new \NumberFormatter($tag, \NumberFormatter::DECIMAL)) -> format($count);
            } catch (\Throwable) {
                continue;
            }

            if (is_string($written) && $written !== '' && !in_array($written, $numerals, true)) {
                $numerals[] = $written;
            }
        }

        return $numerals;
    }

    /**
     * Whether a string is an address rather than language: a URL, or a bare
     * hostname. The same in every language by definition, and a model asked
     * for one anyway translates the words inside it or grows words onto it.
     */
    public static function isInvariant(string $english): bool
    {
        $english = trim($english);

        // The whole string, not a string with one in it: a sentence that
        // carries a link still has words around it to translate.
        if (preg_match('#^\S+://\S+$#', $english) === 1) {
            return true;
        }

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $english) === 1;
    }

    /**
     * Whether an answer is the question handed back, in a language that cannot
     * have written it.
     *
     * A model that has nothing to say for a string returns it unchanged, and
     * stored that way the locale file claims a translation it has not got. Only
     * asked of a language written in another script, because that is where an
     * unchanged answer is proof rather than suspicion: German for "Video" is
     * "Video", and refusing that would be refusing a correct translation.
     */
    public static function isUntouched(string $locale, string $asked, string $translated): bool
    {
        // Judged on the words only. A pattern is placeholders and punctuation,
        // and the names inside the braces are the code's rather than anything a
        // language has a word for, so it comes back unchanged when it is right.
        $words = (string) preg_replace('/\{[a-zA-Z]+\}/', '', $asked);

        if (trim($asked) !== trim($translated) || !preg_match('/\p{L}/u', $words)) {
            return false;
        }

        return !self::writesInLatin($locale);
    }

    /**
     * Whether a language is written in the same alphabet the source is.
     *
     * Read off the name the language calls itself by, which is a sample of the
     * language written in its own script and one ICU already holds - "Deutsch"
     * against "русский", "العربية", "日本語". A language tag carries no script
     * of its own unless somebody wrote one into it.
     */
    private static function writesInLatin(string $locale): bool
    {
        static $latin = [];

        if (isset($latin[$locale])) {
            return $latin[$locale];
        }

        $named = \Locale::getDisplayLanguage($locale, $locale);

        // An unknown language is taken as Latin, which is the answer that
        // refuses nothing: a guess is not grounds for throwing a translation
        // away.
        if (!is_string($named) || $named === '' || $named === $locale) {
            return $latin[$locale] = true;
        }

        return $latin[$locale] = preg_match('/\p{L}/u', $named) === 1
            && preg_match('/[^\p{Latin}\p{Common}\p{Inherited}]/u', $named) === 0;
    }

    /**
     * A counted phrasing finished off in the language's own words.
     *
     * A locale either writes every form its rule can ask for or writes none:
     * half a set falls back to English for the rest, so a reader sees one
     * count in their language and the next in English, in the same place on
     * the same page. Where a form went untranslated the language's own plural
     * stands in, which is at worst the wrong case of the right words.
     *
     * Nothing is invented for a set the locale has no words for at all - that
     * one falls back to English whole, which is what a locale nobody has
     * finished is supposed to do.
     *
     * @param array<string, string> $translations
     * @param array<string, array<string, string>> $sets English's counted entries
     * @return array<string, string>
     */
    public static function completed(array $translations, string $locale, array $sets): array
    {
        foreach (array_keys($sets) as $prefix) {
            $written = [];

            foreach (PluralRule::CATEGORIES as $category) {
                if (isset($translations[$prefix . '.' . $category])) {
                    $written[$category] = $translations[$prefix . '.' . $category];
                }
            }

            if ($written === []) {
                continue;
            }

            $plural = $written['other'] ?? reset($written);
            $categories = PluralRule::categoriesFor($locale);

            foreach ($categories as $category) {
                if (!isset($written[$category])) {
                    $translations[$prefix . '.' . $category] = $plural;
                }
            }

            // Exactly the forms this language counts in, so a set carries no
            // form a reader can never reach. "Other" stays regardless, being
            // what a missing form falls back to.
            foreach (array_keys($written) as $category) {
                if ($category !== 'other' && !in_array($category, $categories, true)) {
                    unset($translations[$prefix . '.' . $category]);
                }
            }
        }

        return $translations;
    }

    /**
     * How many counted forms read the same as this language's plural where
     * English's did not.
     *
     * A form the model would not give stands in from the language's own plural,
     * and once written it looks like a translation - so the distinction those
     * forms exist to make is gone and nothing says so. Counted against English
     * rather than on its own, because a language whose word does not inflect
     * writes the same form for every count and is right to.
     *
     * @param array<string, string> $translations
     * @param array<string, array<string, string>> $sets English's counted entries
     */
    public static function collapsed(array $translations, array $sets): int
    {
        $collapsed = 0;

        foreach ($sets as $prefix => $forms) {
            $plural = $translations[$prefix . '.other'] ?? null;

            if ($plural === null || !isset($forms['other'])) {
                continue;
            }

            foreach (PluralRule::CATEGORIES as $category) {
                if ($category === 'other' || !isset($translations[$prefix . '.' . $category])) {
                    continue;
                }

                // Skipped only where English says the same thing for both, so
                // there was never a distinction to lose here.
                $same_in_english = isset($forms[$category]) && $forms[$category] === $forms['other'];

                if ($translations[$prefix . '.' . $category] === $plural && !$same_in_english) {
                    $collapsed++;
                }
            }
        }

        return $collapsed;
    }

    /**
     * The source's shape with translations in place of its strings, leaving out
     * what has none. Built by walking the source so the file comes out in the
     * order it was written in rather than the order it was translated in.
     *
     * A locale file holds more than language, and the rest of it is the
     * locale's own rather than a translation of English's: the clock it tells
     * the time on. That is taken from $existing, because rebuilding the file
     * out of English's shape and the strings that came back would otherwise
     * drop it.
     *
     * @param array<string, mixed> $source
     * @param array<string, string> $translations
     * @param array<string, mixed> $existing what this locale already said
     * @return array<string, mixed>
     */
    public static function merge(array $source, array $translations, array $existing = [], string $prefix = ''): array
    {
        $merged = [];

        foreach ($source as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $branch = self::merge(
                    $value,
                    $translations,
                    is_array($existing[$key] ?? null) ? $existing[$key] : [],
                    $path
                );

                if ($branch !== []) {
                    $merged[$key] = $branch;
                }
            } elseif (!is_string($value)) {
                // A number is a fact about the locale - which clock it counts
                // hours on - so its own answer stands and English's is only
                // what a locale without one gets.
                $merged[$key] = $existing[$key] ?? $value;
            } elseif (isset($translations[$path])) {
                // Present rather than non-empty: a key nobody has translated
                // is absent here, so what is left is either a translation or
                // a language's deliberate blank, and both belong in the file.
                $merged[$key] = $translations[$path];
            }
        }

        // What this language has and English has no key for at all. Polish
        // counts in four forms where English counts in two, and a language
        // whose word order puts the link at the other end of the sentence
        // carries the fragment on the other side of it. A file built from
        // English alone would delete both, every run.
        foreach (self::beyondSource($translations, $prefix, $source) as $key => $value) {
            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * The translations directly under $prefix that the source has no key for.
     *
     * Keyed on the branch rather than the exact path, so a language is only
     * allowed the extra forms of something English still has - a class English
     * has dropped is walked by nobody and goes with it.
     *
     * @param array<string, string> $translations
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private static function beyondSource(array $translations, string $prefix, array $source): array
    {
        $head = $prefix === '' ? '' : $prefix . '.';
        $beyond = [];

        foreach ($translations as $path => $value) {
            if ($head !== '' && !str_starts_with($path, $head)) {
                continue;
            }

            $key = substr($path, strlen($head));

            if ($key === '' || $value === '' || str_contains($key, '.') || array_key_exists($key, $source)) {
                continue;
            }

            $beyond[$key] = $value;
        }

        return $beyond;
    }

    /**
     * What ICU already knows, for the keys that are calendar data rather than
     * language.
     *
     * A month name is not a translation, it is a fact about a locale, and a
     * model asked for one word with no sentence around it degenerates: it
     * answered "Januaro Januaro Januaro Januaro" in Esperanto and "MarIndian
     * National month 10 - ShortName" in Catalan. ICU has the real answer for
     * every locale it knows, and English stands for the ones it does not.
     */
    public static function fromCalendar(string $locale, string $path): ?string
    {
        if (preg_match('/^DateFormat\.(months|shortMonths)\.([0-9]{1,2})$/', $path, $found) === 1) {
            return self::month(
                $locale,
                (int) $found[2],
                $found[1] === 'months' ? \IntlDateFormatter::LONG : \IntlDateFormatter::MEDIUM
            );
        }

        if ($path === 'DateFormat.am' || $path === 'DateFormat.pm') {
            return self::formatted($locale, 'a', mktime($path === 'DateFormat.am' ? 9 : 21, 0, 0, 1, 15, 2026));
        }

        // The date formats are patterns rather than sentences, and the order of
        // their parts is the locale's, not a translator's opinion: Spanish
        // writes "d 'de' MMMM 'de' y" and Japanese "y年M月d日". Translated
        // instead of read from ICU, every language renders dates in US order.
        //
        // "short" here is ICU's medium: the one that abbreviates the month
        // rather than numbering it, which is what shortMonths is a list of.
        $patterns = [
            'DateFormat.long' => [\IntlDateFormatter::LONG, \IntlDateFormatter::NONE],
            'DateFormat.short' => [\IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE],
            'DateFormat.time' => [\IntlDateFormatter::NONE, \IntlDateFormatter::SHORT],
        ];

        if (isset($patterns[$path])) {
            return self::placeholders($locale, $patterns[$path][0], $patterns[$path][1]);
        }

        if ($path === 'DateFormat.dateAndTime') {
            return self::joined($locale);
        }

        return null;
    }

    /**
     * How this locale writes a date and a time together.
     *
     * Two placeholders and a joiner is all it is, so there is nothing in it for
     * a model to translate - it hands the pattern straight back - and the
     * joiner is a fact about the locale anyway: French writes "à" between them
     * and Chinese writes nothing at all. ICU composes the two patterns itself,
     * so the answer is the combined pattern with each half put back as its
     * placeholder.
     */
    private static function joined(string $locale): ?string
    {
        $both = self::pattern($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT);
        $date = self::pattern($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        $time = self::pattern($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT);

        if ($both === null || $date === null || $time === null) {
            return null;
        }

        $written = str_replace([$date, $time], ['{date}', '{time}'], $both);

        // Only where both halves were found whole: a locale whose combined
        // pattern is not simply its two patterns with something between them
        // would otherwise have half a pattern left in it.
        if (!str_contains($written, '{date}') || !str_contains($written, '{time}')) {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', self::unquoted($written)) ?? '');
    }

    /** This locale's raw ICU pattern for a date style, a time style, or both. */
    private static function pattern(string $locale, int $date, int $time): ?string
    {
        try {
            $pattern = (new \IntlDateFormatter($locale, $date, $time, 'UTC')) -> getPattern();
        } catch (\Throwable) {
            return null;
        }

        return is_string($pattern) && $pattern !== '' ? $pattern : null;
    }

    /**
     * ICU's quoting taken off the words between the fields - the "at" in
     * "{date} 'at' {time}" is a word this language says, not punctuation.
     */
    private static function unquoted(string $pattern): string
    {
        $written = '';
        $length = strlen($pattern);

        for ($at = 0; $at < $length;) {
            if ($pattern[$at] !== "'") {
                $written .= $pattern[$at];
                $at++;

                continue;
            }

            $end = strpos($pattern, "'", $at + 1);

            if ($end === false) {
                break;
            }

            // Two together are an apostrophe this language writes, rather than
            // an empty literal.
            $written .= $end === $at + 1 ? "'" : substr($pattern, $at + 1, $end - $at - 1);
            $at = $end + 1;
        }

        return $written;
    }

    /**
     * Whether this key is calendar data, which is ICU's whether or not ICU
     * knows the locale.
     *
     * Asked separately from fromCalendar(), because a locale ICU has never
     * heard of answers nothing there - and a model must still not be asked
     * for a month name. An English month beats an invented one.
     */
    public static function isCalendar(string $path): bool
    {
        return in_array(
            $path,
            [
                'DateFormat.am', 'DateFormat.pm', 'DateFormat.long',
                'DateFormat.short', 'DateFormat.time', 'DateFormat.dateAndTime',
            ],
            true
        ) || preg_match('/^DateFormat\.(months|shortMonths)\.[0-9]{1,2}$/', $path) === 1;
    }

    /**
     * A month as this locale's own date pattern will use it.
     *
     * The name and the pattern are chosen together, because the pattern is the
     * only place the name ever lands - DateFormat puts it in {month} and so
     * does its JavaScript twin. The standalone name is the wrong one for that
     * job in every language that inflects: Polish writes "15 stycznia 2026"
     * and declines the month to do it, where standalone is "styczeń", and
     * Catalan's pattern carries no "de" because its own month name does.
     *
     * Where the pattern counts months rather than naming them - Japanese
     * "y年M月d日" - the number is the answer. A name there prints the 月 twice.
     */
    private static function month(string $locale, int $number, int $style): ?string
    {
        $field = self::monthField($locale, $style);

        if ($field === null) {
            return null;
        }

        [$letter, $width] = $field;

        if ($width <= 2) {
            return (string) $number;
        }

        return self::formatted($locale, str_repeat($letter, $width), mktime(0, 0, 0, $number, 15, 2026));
    }

    /**
     * The month field this locale's pattern uses, as its letter and its width.
     *
     * @return array{0: string, 1: int}|null
     */
    private static function monthField(string $locale, int $style): ?array
    {
        try {
            $pattern = (new \IntlDateFormatter($locale, $style, \IntlDateFormatter::NONE, 'UTC')) -> getPattern();
        } catch (\Throwable) {
            return null;
        }

        if (!is_string($pattern) || $pattern === '') {
            return null;
        }

        $length = strlen($pattern);

        for ($at = 0; $at < $length; $at++) {
            // Skipped whole: a quoted literal holds a language's connecting
            // words, and an M in one of those is a letter rather than a field.
            if ($pattern[$at] === "'") {
                $end = strpos($pattern, "'", $at + 1);

                if ($end === false) {
                    break;
                }

                $at = $end;

                continue;
            }

            if ($pattern[$at] === 'M' || $pattern[$at] === 'L') {
                $run = 0;

                while ($at + $run < $length && $pattern[$at + $run] === $pattern[$at]) {
                    $run++;
                }

                return [$pattern[$at], $run];
            }
        }

        return null;
    }

    /** An ICU pattern for this locale, written in the placeholders a locale file uses. */
    private static function placeholders(string $locale, int $date, int $time): ?string
    {
        try {
            $pattern = (new \IntlDateFormatter($locale, $date, $time, 'UTC')) -> getPattern();
        } catch (\Throwable) {
            return null;
        }

        if (!is_string($pattern) || $pattern === '') {
            return null;
        }

        $fields = [
            'y' => '{year}', 'M' => '{month}', 'L' => '{month}', 'd' => '{day}',
            'H' => '{hour}', 'h' => '{hour}', 'K' => '{hour}', 'k' => '{hour}',
            'm' => '{minute}', 'a' => '{meridiem}', 'b' => '{meridiem}',
        ];

        $written = '';
        $length = strlen($pattern);

        for ($at = 0; $at < $length;) {
            $character = $pattern[$at];

            // ICU quotes its literals, and a language's connecting words live
            // in them - the "de" in "d 'de' MMMM 'de' y".
            if ($character === "'") {
                $end = strpos($pattern, "'", $at + 1);

                if ($end === false) {
                    break;
                }

                $written .= $end === $at + 1 ? "'" : substr($pattern, $at + 1, $end - $at - 1);
                $at = $end + 1;

                continue;
            }

            if (ctype_alpha($character)) {
                $run = 0;

                while ($at + $run < $length && $pattern[$at + $run] === $character) {
                    $run++;
                }

                // A field with no placeholder to carry it - a weekday in a
                // pattern that wants one - is left out rather than invented.
                $written .= $fields[$character] ?? '';
                $at += $run;

                continue;
            }

            $written .= $character;
            $at++;
        }

        $written = trim(preg_replace('/\s+/u', ' ', $written) ?? '');

        return $written === '' ? null : $written;
    }

    private static function formatted(string $locale, string $pattern, int $when): ?string
    {
        try {
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                'UTC',
                null,
                $pattern
            );
            $formatted = $formatter -> format($when);
        } catch (\Throwable) {
            return null;
        }

        return is_string($formatted) && trim($formatted) !== '' ? $formatted : null;
    }

    /**
     * A string with its placeholders swapped for sentinels a model leaves be,
     * and the swaps needed to put them back.
     *
     * A model reads "{count}" as a word: it translates what is inside the
     * braces and drops the rest of the sentence doing it, so "{count} views"
     * comes back as "{consultas}". "X1X" survives whole, and the sentence
     * reorders around it the way the language wants - "X1X views" becomes
     * "Vistas X1X".
     *
     * A unit written against the number goes with it. "{count}m ago" masked to
     * "X1Xm ago" leaves the tokenizer one word, "X1Xm", which comes back fused
     * - the unit dropped, uppercased, or carrying a piece of the sentinel with
     * it. Masking "{count}m" whole means the unit is not translated, which is
     * the lesser loss: a language that abbreviates minutes differently can be
     * given that by hand, where a corrupted one has to be found first.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    public static function mask(string $text): array
    {
        preg_match_all('/[a-zA-Z]*\{[a-zA-Z]+\}[a-zA-Z]*/', $text, $found);

        $sentinels = [];
        $masked = $text;

        foreach (array_values(array_unique($found[0])) as $index => $placeholder) {
            $sentinel = 'X' . ($index + 1) . 'X';
            $sentinels[$sentinel] = $placeholder;
            $masked = str_replace($placeholder, $sentinel, $masked);
        }

        return [$masked, $sentinels];
    }

    /** @param array<string, string> $sentinels */
    public static function unmask(string $text, array $sentinels): string
    {
        return str_replace(array_keys($sentinels), array_values($sentinels), $text);
    }

    /**
     * The English handed to the model for one key of one locale.
     *
     * Normally the English is just the English. The language prompt is the
     * exception: it is the one string shown to somebody who cannot read the
     * page, offering them the language they can, so each locale's copy has to
     * name its own language rather than the source's. Translating "in English"
     * into Spanish yields a Spanish sentence offering English, which is the
     * opposite of the point.
     *
     * The name is put into the English before translating rather than
     * substituted into the result afterwards, because languages inflect it -
     * "in het Nederlands", "w języku polskim" - and only translating the whole
     * sentence gets that right.
     */
    public static function sourceFor(string $locale, string $path, string $english): string
    {
        if ($path !== 'LanguagePrompt.question') {
            return $english;
        }

        $named = \Locale::getDisplayName($locale, Strings::SOURCE_LOCALE);

        // ICU hands back the tag itself for a language it does not know, which
        // would leave the sentence offering English - the one answer that makes
        // the prompt pointless.
        if ($named === '' || $named === $locale) {
            return $english;
        }

        return str_replace(
            \Locale::getDisplayLanguage(Strings::SOURCE_LOCALE, Strings::SOURCE_LOCALE),
            $named,
            $english
        );
    }

    /**
     * The counted noun's number put back where English had it.
     *
     * Handed "X1X views" a model often answers "Vistas X1X". Every language
     * here leads with the number it counts by, and "Vistas 5" reads as though
     * the 5 were part of the label rather than the count.
     */
    public static function numberFirst(string $english, string $translated): string
    {
        if (preg_match('/^\{[a-zA-Z]+\}/', $english, $found) !== 1) {
            return $translated;
        }

        $placeholder = $found[0];
        $tidied = rtrim($translated);

        if (str_starts_with($tidied, $placeholder) || !str_ends_with($tidied, $placeholder)) {
            return $translated;
        }

        $rest = trim(mb_substr($tidied, 0, -mb_strlen($placeholder)));

        return $rest === '' ? $translated : $placeholder . ' ' . $rest;
    }

    /**
     * The translation wearing the edge whitespace its English wears.
     *
     * A trailing space can be structural: "{count} people voted " ends in one
     * because the deadline is appended right after it, and an answer that
     * comes back without it glues two sentences into one word. Models rarely
     * echo edge whitespace, and the punctuation pass trims it besides - so it
     * is put back from the English, which is the one place it is deliberate.
     */
    public static function spacedAsSource(string $english, string $translated): string
    {
        preg_match('/^\s*/u', $english, $leading);
        preg_match('/\s*$/u', $english, $trailing);

        return ($leading[0] ?? '') . trim($translated) . ($trailing[0] ?? '');
    }

    /**
     * The translation punctuated the way its English is.
     *
     * A model finishes a sentence even when it was handed a label: "Yes"
     * comes back as "Ja." and "Sí.", and a button with a full stop on it reads
     * as prose. Only endings the source did not have are taken off, so real
     * sentences keep theirs.
     */
    public static function punctuatedAsSource(string $english, string $translated): string
    {
        // Not every language ends a sentence with a full stop: Urdu closes
        // with one mark, Devanagari with another, and a label that gained
        // either is as wrong as one that gained a period. The ellipsis is in
        // the class because English writes labels with it - "Loading…" - and
        // an answer whose own ellipsis was stripped as an invented full stop
        // loses the very mark the label was written with.
        $ending = '/[.!?。！？۔।॥…]+$/u';

        if (preg_match($ending, trim($english)) === 1) {
            return $translated;
        }

        $trimmed = rtrim((string) preg_replace($ending, '', trim($translated)));

        return $trimmed === '' ? $translated : $trimmed;
    }

    /**
     * Whether an answer has collapsed into repeating itself.
     *
     * A model given one or two words with no sentence around them stutters -
     * "Log In Log In Log In Log In" - and no decoding discipline stops it:
     * ctranslate2's own repetition penalties turn it into "Log In Log in".
     * These are refused rather than salvaged, because an English label beats
     * a stutter in Turkish.
     */
    public static function isDegenerate(string $english, string $translated): bool
    {
        if (mb_strlen($translated) > 6 * mb_strlen($english) + 10) {
            return true;
        }

        // The same word twice in a row. A label two words long never trips the
        // length test above, and "はい はい", "Titolo Titolo" and "Password
        // Password" are all the same stutter as the long ones. Repetition that
        // is not consecutive is left alone, because that is grammar rather
        // than a fault - Catalan says "de base de dades" and means it.
        $words = preg_split('/\s+/u', trim($translated)) ?: [];

        for ($at = 1; $at < count($words); $at++) {
            if ($words[$at] !== '' && mb_strtolower($words[$at]) === mb_strtolower($words[$at - 1])) {
                return true;
            }
        }

        // A window repeated often enough to be most of the answer. Counting
        // repeats alone condemns ordinary prose - "vous" falls four times in
        // two French sentences - so what matters is how much of the text the
        // repetition accounts for.
        $length = mb_strlen($translated);

        foreach ([6, 8] as $size) {
            for ($at = 0; $at < $length - $size; $at++) {
                $window = mb_substr($translated, $at, $size);

                if (trim($window) === '') {
                    continue;
                }

                $repeats = mb_substr_count($translated, $window);

                if ($repeats > 3 && $repeats * $size > $length * 0.4) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a translation came back with exactly the {placeholder}s the
     * English had, and no wreckage of the masking.
     *
     * Nothing survives a model by assumption. A sentinel can come back
     * lowercased, split, doubled or invented outright, and each of those makes
     * a different mess: a lost placeholder renders the sentence with a hole in
     * it, a doubled one prints the number twice, and a leftover "X1X" shows the
     * reader the scaffolding. Counting them is what tells the three apart. A
     * string that fails any of it is thrown away and English stands in for it.
     */
    public static function keepsPlaceholders(string $english, string $translated): bool
    {
        preg_match_all('/\{[a-zA-Z]+\}/', $english, $wanted);

        foreach (array_unique($wanted[0]) as $placeholder) {
            if (substr_count($translated, $placeholder) !== substr_count($english, $placeholder)) {
                return false;
            }
        }

        // No source string is written with one, so anything of this shape is a
        // sentinel the model changed enough that it never mapped back.
        return preg_match('/X[0-9]+X/', $translated) === 0;
    }

    /**
     * The paths a locale needs translating: the ones it has never had, and the
     * ones whose English has been edited since it was last translated.
     *
     * @param array<string, string> $source English, by path
     * @param array<string, string> $translated what the locale already says
     * @param array<string, string> $fingerprints the English each of those was made from
     * @return array<string, string>
     */
    public static function stale(array $source, array $translated, array $fingerprints): array
    {
        $stale = [];

        foreach ($source as $path => $english) {
            if (!isset($translated[$path]) || ($fingerprints[$path] ?? '') !== self::fingerprint($english)) {
                $stale[$path] = $english;
            }
        }

        return $stale;
    }

    public static function fingerprint(string $english): string
    {
        return sha1($english);
    }

    /**
     * The fingerprints, with a record added for every translation that has
     * none - taking it as made from the English beside it.
     *
     * Nothing else can be assumed about a translation nobody kept a record
     * for, and the alternative is retranslating it: overwriting whatever
     * somebody wrote by hand with whatever a model says today. Polish's "few"
     * and "many" were exactly that - written by hand years before any of this
     * existed - and a pass replaced both with one machine phrasing, which is
     * the distinction those forms exist to make, gone.
     *
     * Recorded rather than merely skipped, so editing the English still marks
     * it stale afterwards. --force is how to ask for it again regardless.
     *
     * @param array<string, string> $existing what this locale already says
     * @param array<string, string> $source English, by path
     * @param array<string, string> $fingerprints
     * @return array<string, string>
     */
    public static function adopting(array $existing, array $source, array $fingerprints): array
    {
        foreach ($existing as $path => $translation) {
            if ($translation !== '' && isset($source[$path]) && !isset($fingerprints[$path])) {
                $fingerprints[$path] = self::fingerprint($source[$path]);
            }
        }

        return $fingerprints;
    }

    /** Translates every locale that needs it, saying what it did as it goes. */
    public function run(): void
    {
        $source = self::flatten(self::read(Strings::SOURCE_LOCALE));

        if ($source === []) {
            throw new \RuntimeException('No source strings in ' . Strings::SOURCE_LOCALE . '.json.');
        }

        foreach ($this -> locales() as $locale) {
            $this -> translate($locale, $source);
        }
    }

    /**
     * The locales to work through: the ones asked for, or every language the
     * site already has a file for.
     *
     * Its own locale files rather than every installed package: a package is
     * only the ability to translate, and writing a file for each one would
     * offer the reader every language Argos happens to ship rather than the
     * ones this site means to be in.
     *
     * @return string[]
     */
    public function locales(): array
    {
        $installed = array_values(array_intersect(
            Strings::available(),
            TranslationWorker::targetsFrom(Strings::SOURCE_LOCALE)
        ));

        if ($this -> only === []) {
            return $installed;
        }

        return array_values(array_intersect($this -> only, $installed));
    }

    /** @param array<string, string> $source */
    private function translate(string $locale, array $source): void
    {
        $source = self::expanded($source, $locale);
        $existing = self::flatten(self::read($locale), '', true);

        // What is already stored stays stored, echoes included - an entry
        // identical to its English renders exactly what the fallback would,
        // and dropping it makes the path stale, which asks the model again:
        // measured, the second answer turned "Cloudflare Turnstile" into
        // Japanese for "Cloudflare's revolving door" and it passed every
        // check. isUntouched() guards what comes in, where a misfire costs
        // nothing; here it would cost whatever the next answer invents.

        $fingerprints = $this -> force
            ? []
            : self::adopting($existing, $source, self::fingerprints($locale));
        $stale = self::stale($source, $existing, $fingerprints);

        if ($this -> paths !== []) {
            $stale = array_intersect_key($stale, array_flip($this -> paths));
        }

        $translations = $existing;
        $kept = 0;
        $wanted = count($stale);

        // Calendar data is answered before the model is asked, not corrected
        // afterwards - it is the one class of string a model reliably ruins.
        foreach ($stale as $path => $english) {
            $known = self::fromCalendar($locale, $path);

            if ($known === null) {
                continue;
            }

            $translations[$path] = $known;
            $fingerprints[$path] = self::fingerprint($english);
            $kept++;
            unset($stale[$path]);
        }

        // What ICU owns but could not answer here is left exactly as it is,
        // rather than falling through to the model that ruins it.
        foreach (array_keys($stale) as $path) {
            if (self::isCalendar($path)) {
                unset($stale[$path]);
            }
        }

        // An address is not language, and a model does not leave one alone:
        // asked, it turned "example.social" into Japanese for "example.societal"
        // and grew a word onto the end of a URL. Never asked; English stands.
        foreach ($stale as $path => $english) {
            if (self::isInvariant($english)) {
                unset($stale[$path]);
            }
        }

        // A blank this language means is an answer, not a gap. Japanese puts
        // the link at the end of the sentence, so the words English writes in
        // front of it have nowhere to go - asked anyway, the model fills the
        // blank and the sentence says its own name twice.
        foreach ($stale as $path => $english) {
            if (($existing[$path] ?? null) !== '') {
                continue;
            }

            $fingerprints[$path] = self::fingerprint($english);
            $kept++;
            unset($stale[$path]);
        }

        $sets = self::counted($source);
        $failure = '';

        // Built only where there is something to ask, because a locale can
        // still have work with nothing stale: a counted set left half written
        // by an earlier run is finished below without a model.
        $worker = $stale === [] ? null : new TranslationWorker(Strings::SOURCE_LOCALE, $locale);

        if ($worker !== null && !$worker -> isAvailable()) {
            echo $locale . ": no package installed\n";

            return;
        }

        foreach (array_chunk($stale, TranslationWorker::BATCH, true) as $chunk) {
            $masked = [];
            $sentinels = [];
            $counts = [];
            $questions = [];

            // Masked from what the model is given, fingerprinted against what
            // en.json says: a locale is stale when the source string changes,
            // not because of what was put into the question for its sake.
            foreach ($chunk as $path => $english) {
                [$questions[$path], $counts[$path]] = self::asked($locale, $path, $english, $sets);
                [$masked[$path], $sentinels[$path]] = self::mask($questions[$path]);
            }

            $answers = $worker -> translate(array_values($masked));
            $paths = array_keys($chunk);

            foreach ($answers as $index => $answer) {
                $path = $paths[$index];
                $english = $chunk[$path];
                $answer = self::unmask($answer, $sentinels[$path]);

                if (self::isUntouched($locale, $questions[$path], $answer)) {
                    unset($fingerprints[$path]);

                    continue;
                }

                if ($counts[$path] !== null) {
                    $answer = self::recounted($answer, $counts[$path], $locale);

                    if ($answer === null) {
                        unset($fingerprints[$path]);

                        continue;
                    }
                }

                $answer = self::spacedAsSource(
                    $english,
                    self::punctuatedAsSource($english, self::numberFirst($english, $answer))
                );

                if (trim($answer) === ''
                    || !self::keepsPlaceholders($english, $answer)
                    || self::isDegenerate($english, $answer)) {
                    unset($fingerprints[$path]);

                    continue;
                }

                $translations[$path] = $answer;
                $fingerprints[$path] = self::fingerprint($english);
                $kept++;
            }
        }

        if ($worker !== null) {
            $failure = $worker -> error();
            $worker -> close();
        }

        // A locale file that exists is a language the site offers, so one with
        // nothing in it would advertise a translation that is entirely English.
        // Judged on what the file would hold rather than on what this run
        // added, because a run whose only work was dropping what an earlier
        // one should never have written still has to save.
        if ($translations === []) {
            echo $locale . ': nothing translated' . ($failure === '' ? '' : ' - ' . $failure) . "\n";

            return;
        }

        $translations = self::completed($translations, $locale, $sets);

        // Said every run rather than only the one that filled them, because a
        // form standing in from the language's own plural is written to the
        // file and looks like a translation from then on - which is how a file
        // that is not finished stops looking unfinished.
        $collapsed = self::collapsed($translations, $sets);
        $standing_in = $collapsed === 0
            ? ''
            : ' - ' . $collapsed . " read the same as this language's plural, check by hand";

        if ($translations === $existing && $kept === 0) {
            echo $locale . ': up to date' . $standing_in . "\n";

            return;
        }

        // What English no longer has a branch for is dropped by merge() not
        // walking it, rather than by matching paths against the source: an
        // English key that is deliberately empty is not in $source at all,
        // and matching would take every language's filled-in version of it.
        $fingerprints = array_intersect_key($fingerprints, $translations);

        self::write($locale, self::merge(self::read(Strings::SOURCE_LOCALE), $translations, self::read($locale)));
        self::writeFingerprints($locale, $fingerprints);

        echo $locale . ': ' . $kept . ' of ' . $wanted . ' translated' . $standing_in . "\n";
    }

    /** @return array<string, mixed> */
    private static function read(string $locale): array
    {
        return self::readJSON(self::path($locale));
    }

    /** @return array<string, string> */
    private static function fingerprints(string $locale): array
    {
        $stored = self::readJSON(self::SOURCES . '/' . $locale . '.json');

        return array_filter($stored, 'is_string');
    }

    /** @return array<string, mixed> */
    private static function readJSON(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException($path . ' is not readable as JSON.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $table */
    private static function write(string $locale, array $table): void
    {
        self::writeJSON(self::path($locale), $table);
    }

    /** @param array<string, string> $fingerprints */
    private static function writeFingerprints(string $locale, array $fingerprints): void
    {
        if (!is_dir(self::SOURCES)) {
            mkdir(self::SOURCES, 0755, true);
        }

        ksort($fingerprints);
        self::writeJSON(self::SOURCES . '/' . $locale . '.json', $fingerprints);
    }

    /** @param array<string, mixed> $table */
    private static function writeJSON(string $path, array $table): void
    {
        $json = json_encode($table, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Could not encode ' . $path . '.');
        }

        // Thrown rather than ignored: a run that saved nothing still counts
        // what it translated, so a write refused for want of permission was
        // reported as "24 of 24 translated" and the files sat unchanged.
        if (file_put_contents($path, $json . "\n") === false) {
            throw new \RuntimeException('Could not write ' . $path . '.');
        }
    }

    private static function path(string $locale): string
    {
        return __DIR__ . '/../../locales/' . $locale . '.json';
    }
}
