<?php

declare(strict_types=1);

/**
 * A translation that renders is not the same as a translation that is right.
 *
 * NoEnglishInClassesTest proves a class holds no words of its own. Nothing
 * proved anything about the words a locale file replaces them with, and the
 * ways those go wrong are silent: a {count} spelled differently is a token
 * printed literally at somebody, and a counted phrase missing the category its
 * own language can ask for falls back to a form that reads wrong in exactly
 * the cases the extra form existed for.
 *
 * Runtime fallback remains a useful last defense for a damaged installation,
 * but a repository locale is complete: every English path has a translation
 * and a fingerprint proving which exact English source it translated.
 */
class LocaleIntegrityTest extends TestCase
{
    /**
     * The categories CLDR defines. A counted entry is recognised by having
     * only these as keys, which no sentence-of-pieces entry does - those are
     * keyed before/link/after and the like.
     *
     * @var string[]
     */
    private const CATEGORIES = ['zero', 'one', 'two', 'few', 'many', 'other'];

    /**
     * The full locale table plus the Help corpus, reassembled under
     * "HelpContent" the way it read before the corpus moved to its own
     * server-only file - so every check below still covers it.
     *
     * @return array<string, mixed>
     */
    private static function table(string $locale): array
    {
        $method = new \ReflectionMethod(Strings::class, 'table');
        $method -> setAccessible(true);
        $table = (array) $method -> invoke(null, $locale);

        $help_method = new \ReflectionMethod(Strings::class, 'helpTable');
        $help_method -> setAccessible(true);
        $table['HelpContent'] = (array) $help_method -> invoke(null, $locale);

        return $table;
    }

    /** @param array<mixed> $entry */
    private static function isCounted(array $entry): bool
    {
        if ($entry === []) {
            return false;
        }

        foreach (array_keys($entry) as $key) {
            if (!in_array($key, self::CATEGORIES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Which categories this language can actually produce. PluralRule samples
     * integers, fractions, and powers of a million so categories used only by
     * Czech decimals or Catalan compact magnitudes are not silently omitted.
     *
     * @return string[]
     */
    private static function categoriesUsedBy(string $locale): array
    {
        return PluralRule::categoriesFor($locale);
    }

    /** The {tokens} in a string, which name a value the code substitutes. @return string[] */
    private static function tokensIn(string $text): array
    {
        preg_match_all('/\{[a-zA-Z]+\}/', $text, $matches);

        $tokens = array_unique($matches[0]);
        sort($tokens);

        return $tokens;
    }

    /**
     * Every leaf in a table, keyed by the path that reaches it.
     *
     * @param array<string, mixed> $table
     * @return array<string, string>
     */
    private static function leaves(array $table, string $prefix = ''): array
    {
        return StringTranslator::flatten($table, $prefix, true);
    }

    private static function valueAtPath(array $table, string $path): mixed
    {
        $value = $table;

        foreach (explode('.', $path) as $step) {
            if (!is_array($value) || !array_key_exists($step, $value)) {
                return null;
            }

            $value = $value[$step];
        }

        return $value;
    }

    /** @param string[] $expected @param string[] $actual */
    private function assertSamePaths(array $expected, array $actual, string $locale, string $kind): void
    {
        sort($expected);
        sort($actual);
        $missing = array_values(array_diff($expected, $actual));
        $extra = array_values(array_diff($actual, $expected));

        $this -> assertTrue(
            $missing === [] && $extra === [],
            $locale . ' has the wrong ' . $kind . ' paths; missing: '
                . implode(', ', array_slice($missing, 0, 10)) . '; extra: '
                . implode(', ', array_slice($extra, 0, 10))
        );
    }

    /**
     * A locale file's name is handed to ICU here and to Intl.PluralRules in
     * the browser, exactly as it is written. A code neither has heard of does
     * not fail - it falls back, and differently on each side: ICU to root,
     * which calls every count "other", and the browser to its own default
     * locale, which is usually English. Brazilian Portuguese filed as "pb"
     * would render "1 vídeos" on the server and "1 vídeo" in the browser, and
     * nothing anywhere would say why.
     */
    public function testEveryLocaleIsOneTheCalendarAndTheCounterKnow(): void
    {
        if (!extension_loaded('intl')) {
            throw new TestSkippedException('needs the intl extension - see the README requirements');
        }

        foreach (Strings::available() as $locale) {
            $language = \Locale::getPrimaryLanguage($locale);

            $this -> assertTrue(
                $language !== ''
                    && \Locale::getDisplayLanguage($language, Strings::SOURCE_LOCALE) !== $language,
                $locale . '.json is not a locale ICU knows, so it would count in the wrong grammar'
            );

            $calendar = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::SHORT,
                'UTC'
            );
            $this -> assertTrue(is_string($calendar -> getPattern()), $locale . ' has no ICU calendar pattern');
        }
    }

    /** Every locale contains exactly the source paths its own plural rules require. */
    public function testEveryLocaleContainsTheCompleteExpandedEnglishSchema(): void
    {
        $source = StringTranslator::flatten(self::table(Strings::SOURCE_LOCALE), '', true);

        foreach (Strings::available() as $locale) {
            if ($locale === Strings::SOURCE_LOCALE) {
                continue;
            }

            $expected = StringTranslator::expanded($source, $locale);
            $actual = StringTranslator::flatten(self::table($locale), '', true);

            $this -> assertSamePaths(array_keys($expected), array_keys($actual), $locale, 'translation');
        }
    }

    /** Every translated path records the exact current English source it translated. */
    public function testEveryTranslationHasACurrentSourceFingerprint(): void
    {
        $source = StringTranslator::flatten(self::table(Strings::SOURCE_LOCALE), '', true);

        foreach (Strings::available() as $locale) {
            if ($locale === Strings::SOURCE_LOCALE) {
                continue;
            }

            $expected_source = StringTranslator::expanded($source, $locale);
            $path = Strings::directory() . '/sources/' . $locale . '.json';
            $contents = is_file($path) ? file_get_contents($path) : false;
            $fingerprints = $contents === false ? null : json_decode($contents, true);

            $this -> assertTrue(is_array($fingerprints), $locale . ' has no readable source-fingerprint file');
            $this -> assertSamePaths(
                array_keys($expected_source),
                array_keys((array) $fingerprints),
                $locale,
                'source-fingerprint'
            );

            foreach ($expected_source as $string_path => $english) {
                $this -> assertSame(
                    StringTranslator::fingerprint($english),
                    $fingerprints[$string_path] ?? null,
                    $locale . ' ' . $string_path . ' was translated from different English wording'
                );
            }
        }
    }

    /** ICU independently selects a branch and Strings returns that real stored branch. */
    public function testPluralSelectionMatchesICUThroughTheRealLocaleTables(): void
    {
        if (!extension_loaded('intl')) {
            throw new TestSkippedException('needs the intl extension - see the README requirements');
        }

        $pattern = '{n, plural, zero{zero} one{one} two{two} few{few} many{many} other{other}}';
        $counts = array_merge(range(0, 130), [200, 201, 1000, 1002, 1005, 1011, 1024]);
        $source = StringTranslator::flatten(self::table(Strings::SOURCE_LOCALE), '', true);
        $counted = StringTranslator::counted($source);

        foreach (Strings::available() as $locale) {
            $formatter = new \MessageFormatter($locale, $pattern);
            $table = self::table($locale);
            Strings::useLocale($locale);

            try {
                foreach ($counted as $entry_path => $_forms) {
                    [$class, $key] = explode('.', $entry_path, 2);

                    foreach ($counts as $count) {
                        $category = $formatter -> format(['n' => $count]);
                        $expected = is_string($category)
                            ? self::valueAtPath($table, $entry_path . '.' . $category)
                            : null;

                        $this -> assertTrue(
                            is_string($expected),
                            $locale . ' ' . $entry_path . ' has no ICU-selected branch for ' . $count
                        );
                        $this -> assertSame(
                            $expected,
                            Strings::plural($class, $key, $count),
                            $locale . ' ' . $entry_path . ' at ' . $count
                        );
                    }
                }
            } finally {
                Strings::useLocale(null);
            }
        }
    }

    /**
     * A locale that has written a counted phrase has written every form its own
     * rule can ask for. Missing one is not a fallback that reads a little
     * wrong - it is the language's own grammar going unsaid for the numbers it
     * was added for.
     */
    public function testACountedPhraseCarriesEveryFormItsLanguageAsksFor(): void
    {
        foreach (Strings::available() as $locale) {
            $categories = self::categoriesUsedBy($locale);

            foreach (self::table($locale) as $class => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                foreach ($entry as $key => $value) {
                    if (!is_array($value) || !self::isCounted($value)) {
                        continue;
                    }

                    foreach ($categories as $category) {
                        $this -> assertTrue(
                            isset($value[$category]),
                            $locale . ' ' . $class . '.' . $key . ' has no "' . $category
                                . '" form, which its own plural rule asks for'
                        );
                    }
                }
            }
        }
    }

    /**
     * A form shared by several integers cannot spell one particular integer
     * into its prose. CLDR puts both 0 and 1 in `one` for languages including
     * Persian and Portuguese; without {count}, zero silently reads as one.
     */
    public function testAPluralFormSharedByCountsPrintsTheCount(): void
    {
        foreach (Strings::available() as $locale) {
            $members = [];

            foreach (range(0, 250) as $count) {
                $members[PluralRule::categoryFor($locale, $count)][] = $count;
            }

            foreach (self::table($locale) as $class => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                foreach ($entry as $key => $value) {
                    if (!is_array($value) || !self::isCounted($value)) {
                        continue;
                    }

                    foreach ($value as $category => $text) {
                        if (count($members[$category] ?? []) < 2 || !is_string($text)) {
                            continue;
                        }

                        $this -> assertTrue(
                            str_contains($text, '{count}'),
                            $locale . ' ' . $class . '.' . $key . '.' . $category
                                . ' serves several counts but does not print {count}'
                        );
                    }
                }
            }
        }
    }

    /**
     * A token names a value the code substitutes, so a translation may only use
     * the ones the code has for that entry. Respell {count} and the reader is
     * shown the brace text itself, while English reads perfectly.
     *
     * Which token belongs where is the language's business, not English's:
     * Portuguese counts 0 and 1 both as "one", so its singular has to say
     * {count} where the English - whose "one" is only ever 1 - hardcodes the
     * numeral. So the rule is that a translation invents no token, not that it
     * carries the same ones.
     */
    public function testATranslationInventsNoTokenTheCodeCannotFill(): void
    {
        $source = self::table(Strings::SOURCE_LOCALE);

        foreach (Strings::available() as $locale) {
            if ($locale === Strings::SOURCE_LOCALE) {
                continue;
            }

            foreach (self::table($locale) as $class => $entry) {
                if (!is_array($entry) || !isset($source[$class])) {
                    continue;
                }

                // Every token the code substitutes anywhere in this class's
                // words - the whole entry, since a counted phrase's forms share
                // one substitution.
                $known = [];

                foreach (self::leaves((array) $source[$class]) as $english) {
                    $known = array_merge($known, self::tokensIn($english));
                }

                foreach (self::leaves($entry) as $path => $text) {
                    foreach (self::tokensIn($text) as $token) {
                        $this -> assertTrue(
                            in_array($token, $known, true),
                            $locale . ' ' . $class . '.' . $path . ' uses ' . $token
                                . ', which the code never puts anything into'
                        );
                    }
                }
            }
        }
    }

    /**
     * A locale file that is a list where English is a sentence renders nothing
     * usable.
     *
     * A counted phrase is the exception, and the reason this cannot simply
     * demand the same paths: how many forms a phrase has is the language's
     * answer, not English's. Polish writes a "few" where English has only "one"
     * and "other", and that extra form is the whole point of the mechanism - so
     * a category the locale's own rule can produce is not a stray key.
     */
    public function testATranslationKeepsTheShapeOfWhatItReplaces(): void
    {
        $source = self::leaves(self::table(Strings::SOURCE_LOCALE));

        foreach (Strings::available() as $locale) {
            if ($locale === Strings::SOURCE_LOCALE) {
                continue;
            }

            $categories = self::categoriesUsedBy($locale);

            foreach (array_keys(self::leaves(self::table($locale))) as $path) {
                if (isset($source[$path])) {
                    continue;
                }

                // The path with its last segment removed, and that segment.
                $cut = strrpos($path, '.');
                $entry = $cut === false ? '' : substr($path, 0, $cut);
                $form = $cut === false ? $path : substr($path, $cut + 1);

                $this -> assertTrue(
                    in_array($form, $categories, true) && self::isCountedInSource($entry),
                    $locale . ' says "' . $path . '", which English has no such string for'
                );
            }
        }
    }

    /** Whether English writes this path as a set of phrasings keyed by category. */
    private static function isCountedInSource(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $entry = self::table(Strings::SOURCE_LOCALE);

        foreach (explode('.', $path) as $step) {
            if (!is_array($entry) || !array_key_exists($step, $entry)) {
                return false;
            }

            $entry = $entry[$step];
        }

        return is_array($entry) && self::isCounted($entry);
    }
}
