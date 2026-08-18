<?php

declare(strict_types=1);

/**
 * The source metadata needed to keep locale files complete and current.
 *
 * Translation remains a human decision. This class only describes the source
 * schema, expands its counted phrases through ICU plural rules, fingerprints
 * exact English wording, and reads clock metadata from ICU's calendar data.
 */
class StringTranslator
{
    /**
     * Every string leaf in a table, keyed by the dotted path that reaches it.
     *
     * @param array<string, mixed> $table
     * @return array<string, string>
     */
    public static function flatten(array $table, string $prefix = '', bool $keep_empty = false): array
    {
        $found = [];

        foreach ($table as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $found += self::flatten($value, $path, $keep_empty);
            } elseif (is_string($value) && ($keep_empty || $value !== '')) {
                $found[$path] = $value;
            }
        }

        return $found;
    }

    /**
     * The plural-form sets in a flattened English source table.
     *
     * @param array<string, string> $source
     * @return array<string, array<string, string>>
     */
    public static function counted(array $source): array
    {
        $found = [];

        foreach ($source as $path => $value) {
            $cut = strrpos($path, '.');

            if ($cut === false) {
                continue;
            }

            $category = substr($path, $cut + 1);

            if (!in_array($category, PluralRule::CATEGORIES, true)) {
                continue;
            }

            $found[substr($path, 0, $cut)][$category] = $value;
        }

        return array_filter(
            $found,
            static fn (array $forms): bool => isset($forms['one'], $forms['other'])
        );
    }

    /**
     * English source paths as a locale must store them after plural expansion.
     *
     * @param array<string, string> $source
     * @return array<string, string>
     */
    public static function expanded(array $source, string $locale): array
    {
        $expanded = $source;

        foreach (self::counted($source) as $path => $forms) {
            foreach (PluralRule::CATEGORIES as $category) {
                unset($expanded[$path . '.' . $category]);
            }

            $categories = array_values(array_unique(array_merge(
                PluralRule::categoriesFor($locale),
                ['other']
            )));

            foreach ($categories as $category) {
                $expanded[$path . '.' . $category] = $forms[$category] ?? $forms['other'];
            }
        }

        ksort($expanded);

        return $expanded;
    }

    /** A source fingerprint changes whenever the exact English wording does. */
    public static function fingerprint(string $english): string
    {
        return sha1($english);
    }

    /** Whether ICU's short-time pattern uses a twelve- or twenty-four-hour clock. */
    public static function clockFromCalendar(string $locale): ?int
    {
        if (!extension_loaded('intl')) {
            return null;
        }

        try {
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::SHORT,
                'UTC'
            );
            $pattern = $formatter -> getPattern();
        } catch (\Throwable) {
            return null;
        }

        if (!is_string($pattern)) {
            return null;
        }

        $quoted = false;
        $length = strlen($pattern);

        for ($position = 0; $position < $length; $position++) {
            $character = $pattern[$position];

            if ($character === "'") {
                if ($position + 1 < $length && $pattern[$position + 1] === "'") {
                    $position++;
                    continue;
                }

                $quoted = !$quoted;
                continue;
            }

            if ($quoted) {
                continue;
            }

            if ($character === 'h' || $character === 'K') {
                return 12;
            }

            if ($character === 'H' || $character === 'k') {
                return 24;
            }
        }

        return null;
    }
}
