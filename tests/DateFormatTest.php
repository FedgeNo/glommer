<?php

declare(strict_types=1);

/**
 * A date, written the way each language writes one.
 *
 * The strings here are also asserted by tests/js/DateFormatTest.js against the
 * client twin. Both sides read one locale entry and must produce one string:
 * a feed holds cards from both renderers at once, and a date that differed
 * between them would be visible in a single column.
 */
class DateFormatTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function words(string $locale): array
    {
        $path = Strings::directory() . '/' . $locale . '.json';
        $table = json_decode((string) file_get_contents($path), true);

        return is_array($table['DateFormat'] ?? null) ? $table['DateFormat'] : [];
    }

    /** @return string[] */
    private static function tokens(string $pattern): array
    {
        preg_match_all('/\{[a-zA-Z]+\}/', $pattern, $matches);
        $tokens = $matches[0];
        sort($tokens);

        return $tokens;
    }

    /** 11 August 2026, 15:04 UTC - a date whose day, month and hour all differ
     *  in shape between the languages here. */
    private static function moment(): int
    {
        return (int) gmmktime(15, 4, 0, 8, 11, 2026);
    }

    private static function inLocale(string $locale, callable $work): string
    {
        Strings::useLocale($locale);

        try {
            return $work();
        } finally {
            Strings::useLocale(null);
        }
    }

    public function testEachLanguageArrangesADateItsOwnWay(): void
    {
        $expected = [
            'en' => 'August 11, 2026',
            'de' => '11. August 2026',
            'es' => '11 de agosto de 2026',
            'fr' => '11 août 2026',
            'pt' => '11 de agosto de 2026',
        ];

        foreach ($expected as $locale => $written) {
            $this -> assertSame($written, self::inLocale($locale, static fn (): string => DateFormat::long(self::moment())), $locale);
        }
    }

    public function testTheShortFormIsTheSameDateWithLessOfIt(): void
    {
        $expected = [
            'en' => 'Aug 11, 2026',
            'de' => '11. Aug. 2026',
            'es' => '11 ago 2026',
            'fr' => '11 août 2026',
            'pt' => '11 ago 2026',
        ];

        foreach ($expected as $locale => $written) {
            $this -> assertSame($written, self::inLocale($locale, static fn (): string => DateFormat::short(self::moment())), $locale);
        }
    }

    /**
     * English keeps a twelve-hour clock and says which half of the day it is;
     * the four beside it run to twenty-four and have no word for that, so their
     * phrasings leave {meridiem} out entirely rather than filling it with
     * something. A meridiem surviving into a 24-hour rendering is the failure
     * this catches.
     */
    public function testTheClockIsTheLanguagesToDecide(): void
    {
        $expected = [
            'en' => 'August 11, 2026 at 3:04 PM',
            'de' => '11. August 2026 um 15:04',
            'es' => '11 de agosto de 2026, 15:04',
            'fr' => '11 août 2026 à 15:04',
            'pt' => '11 de agosto de 2026 às 15:04',
        ];

        foreach ($expected as $locale => $written) {
            $this -> assertSame($written, self::inLocale($locale, static fn (): string => DateFormat::longWithTime(self::moment())), $locale);
        }
    }

    /**
     * A single-digit day is written as one digit. Every other date here has a
     * two-digit day, which a renderer that zero-padded would render correctly
     * by accident.
     */
    public function testASingleDigitDayIsNotPadded(): void
    {
        $moment = (int) gmmktime(9, 4, 0, 8, 5, 2026);

        $this -> assertSame('August 5, 2026', self::inLocale('en', static fn (): string => DateFormat::long($moment)));
        $this -> assertSame('5. August 2026', self::inLocale('de', static fn (): string => DateFormat::long($moment)));
        $this -> assertSame('5 de agosto de 2026', self::inLocale('es', static fn (): string => DateFormat::long($moment)));

        // The hour is not padded on a twelve-hour clock either.
        $this -> assertSame('9:04 AM', self::inLocale('en', static fn (): string => DateFormat::time($moment)));
    }

    /** Noon and midnight are where a twelve-hour clock goes wrong. */
    public function testMiddayAndMidnightReadAsTwelve(): void
    {
        $midnight = gmmktime(0, 30, 0, 7, 30, 2026);
        $midday = gmmktime(12, 5, 0, 7, 30, 2026);

        $this -> assertSame('12:30 AM', self::inLocale('en', static fn (): string => DateFormat::time((int) $midnight)));
        $this -> assertSame('12:05 PM', self::inLocale('en', static fn (): string => DateFormat::time((int) $midday)));

        // The same two on a twenty-four hour clock, which zero-pads instead.
        $this -> assertSame('00:30', self::inLocale('de', static fn (): string => DateFormat::time((int) $midnight)));
        $this -> assertSame('12:05', self::inLocale('de', static fn (): string => DateFormat::time((int) $midday)));
    }

    /** Every month is named, in every language - a gap renders a date with a hole in it. */
    public function testNoMonthGoesUnnamed(): void
    {
        foreach (Strings::available() as $locale) {
            for ($month = 1; $month <= 12; $month++) {
                $moment = (int) gmmktime(12, 0, 0, $month, 15, 2026);

                foreach (['long', 'short'] as $shape) {
                    $written = self::inLocale($locale, static fn (): string => DateFormat::$shape($moment));

                    $this -> assertTrue(
                        !str_contains($written, '{') && trim($written) !== '',
                        $locale . ' month ' . $month . ' (' . $shape . ') came out as "' . $written . '"'
                    );
                }
            }
        }
    }

    /** Every stored calendar has the complete shape both renderers require. */
    public function testEveryLocaleHasACompleteCalendarShape(): void
    {
        foreach (Strings::available() as $locale) {
            $words = self::words($locale);

            foreach (['months', 'shortMonths'] as $list) {
                $months = $words[$list] ?? null;
                $this -> assertTrue(is_array($months), $locale . ' has no ' . $list);
                $this -> assertSame(range(1, 12), array_keys((array) $months), $locale . ' ' . $list . ' keys');

                foreach ((array) $months as $month => $name) {
                    $this -> assertTrue(
                        is_string($name) && trim($name) !== '',
                        $locale . ' ' . $list . ' month ' . $month . ' is empty'
                    );
                }
            }

            $this -> assertSame(
                ['{day}', '{month}', '{year}'],
                self::tokens((string) ($words['long'] ?? '')),
                $locale . ' long date tokens'
            );
            $this -> assertSame(
                ['{day}', '{month}', '{year}'],
                self::tokens((string) ($words['short'] ?? '')),
                $locale . ' short date tokens'
            );
            $this -> assertSame(
                ['{date}', '{time}'],
                self::tokens((string) ($words['dateAndTime'] ?? '')),
                $locale . ' combined date/time tokens'
            );

            $clock = $words['clock'] ?? null;
            $expected_time_tokens = $clock === 12
                ? ['{hour}', '{meridiem}', '{minute}']
                : ['{hour}', '{minute}'];
            sort($expected_time_tokens);

            $this -> assertSame($expected_time_tokens, self::tokens((string) ($words['time'] ?? '')), $locale . ' time tokens');
            $this -> assertSame(
                StringTranslator::clockFromCalendar($locale),
                $clock,
                $locale . ' clock differs from ICU short-time data'
            );
        }
    }
}
