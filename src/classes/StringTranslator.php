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
     * Only strings: a locale carries numbers ("clock": 12) and the counting
     * rule as well, and neither is language a model has anything to say about.
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
            if ($prefix === '' && $key === Strings::PLURAL_RULE) {
                continue;
            }

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
     * The source's shape with translations in place of its strings, leaving out
     * what has none. Built by walking the source so the file comes out in the
     * order it was written in rather than the order it was translated in.
     *
     * A locale file holds more than language, and the rest of it is the
     * locale's own rather than a translation of English's: the rule it counts
     * by, and the clock it tells the time on. Those are taken from $existing,
     * because rebuilding the file out of English's shape and the strings that
     * came back would otherwise drop every one of them.
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

            // The counting rule is code, and each language's own: English's
            // would have Polish counting in two forms where it has four.
            if ($prefix === '' && $key === Strings::PLURAL_RULE) {
                if (array_key_exists($key, $existing)) {
                    $merged[$key] = $existing[$key];
                }

                continue;
            }

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

        return null;
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
            ['DateFormat.am', 'DateFormat.pm', 'DateFormat.long', 'DateFormat.short', 'DateFormat.time'],
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
     * @return array{0: string, 1: array<string, string>}
     */
    public static function mask(string $text): array
    {
        preg_match_all('/\{[a-zA-Z]+\}/', $text, $found);

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
        // either is as wrong as one that gained a period.
        $ending = '/[.!?。！？۔।॥]+$/u';

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
        $existing = self::flatten(self::read($locale), '', true);
        $fingerprints = $this -> force ? [] : self::fingerprints($locale);
        $stale = self::stale($source, $existing, $fingerprints);

        if ($this -> paths !== []) {
            $stale = array_intersect_key($stale, array_flip($this -> paths));
        }

        if ($stale === []) {
            echo $locale . ": up to date\n";

            return;
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

        $worker = new TranslationWorker(Strings::SOURCE_LOCALE, $locale);

        if ($stale !== [] && !$worker -> isAvailable()) {
            echo $locale . ": no package installed\n";

            return;
        }

        foreach (array_chunk($stale, TranslationWorker::BATCH, true) as $chunk) {
            $masked = [];
            $sentinels = [];

            // Masked from what the model is given, fingerprinted against what
            // en.json says: a locale is stale when the source string changes,
            // not because the name of its own language was put into it.
            foreach ($chunk as $path => $english) {
                [$masked[$path], $sentinels[$path]] = self::mask(self::sourceFor($locale, $path, $english));
            }

            $answers = $worker -> translate(array_values($masked));
            $paths = array_keys($chunk);

            foreach ($answers as $index => $answer) {
                $path = $paths[$index];
                $english = $chunk[$path];
                $answer = self::punctuatedAsSource(
                    $english,
                    self::numberFirst($english, self::unmask($answer, $sentinels[$path]))
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

        $failure = $worker -> error();
        $worker -> close();

        // A locale file that exists is a language the site offers, so one with
        // nothing in it would advertise a translation that is entirely English.
        // Better to write nothing and say why.
        if ($kept === 0) {
            echo $locale . ': nothing translated' . ($failure === '' ? '' : ' - ' . $failure) . "\n";

            return;
        }

        // What English no longer has a branch for is dropped by merge() not
        // walking it, rather than by matching paths against the source: an
        // English key that is deliberately empty is not in $source at all,
        // and matching would take every language's filled-in version of it.
        $fingerprints = array_intersect_key($fingerprints, $translations);

        self::write($locale, self::merge(self::read(Strings::SOURCE_LOCALE), $translations, self::read($locale)));
        self::writeFingerprints($locale, $fingerprints);

        echo $locale . ': ' . $kept . ' of ' . $wanted . " translated\n";
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

        file_put_contents($path, $json . "\n");
    }

    private static function path(string $locale): string
    {
        return __DIR__ . '/../../locales/' . $locale . '.json';
    }
}
