<?php

declare(strict_types=1);

/**
 * Polish. Anything not written here falls back to src/locales/en.php.
 *
 * Polish has four plural categories rather than English's two: 'one' for
 * exactly 1, 'few' for a count ending in 2-4 (but not 12-14), 'many' for
 * everything else an integer can be (0, 5-21, 25-31, ...), and 'other' for a
 * fractional count no integer here ever produces. Every counted phrasing below
 * carries all four forms its own rule can ask for - see
 * tests/LocaleIntegrityTest.php, which finds them by calling the rule itself
 * rather than by trusting a list of keys.
 */

return [
    Strings::PLURAL_RULE => static function (int $count): string {
        if ($count === 1) {
            return 'one';
        }

        $lastDigit = $count % 10;
        $lastTwoDigits = $count % 100;

        if ($lastDigit >= 2 && $lastDigit <= 4 && ($lastTwoDigits < 12 || $lastTwoDigits > 14)) {
            return 'few';
        }

        if ($lastDigit === 0 || $lastDigit === 1 || ($lastDigit >= 5 && $lastDigit <= 9) || ($lastTwoDigits >= 12 && $lastTwoDigits <= 14)) {
            return 'many';
        }

        return 'other';
    },

    'PollOptionVotes' => [
        'votes' => [
            'one' => '1 głos',
            'few' => '{count} głosy',
            'many' => '{count} głosów',
            'other' => '{count} głosu',
        ],
    ],

    'LoginPrompt' => [
        'reply' => ['before' => '', 'link' => 'Zaloguj się', 'after' => ', aby odpowiedzieć.'],
        'post' => ['before' => '', 'link' => 'Zaloguj się', 'after' => ', aby opublikować.'],
    ],
];
