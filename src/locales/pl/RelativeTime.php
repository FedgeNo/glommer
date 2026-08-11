<?php

declare(strict_types=1);

/**
 * How long ago something happened, in Polish.
 *
 * "min" and "godz." are abbreviations, not inflected words, so they read fine
 * unchanged across every count the way English's own "m"/"h" do. "dzień" is
 * short enough that Polish does not abbreviate it the same way, and the
 * unabbreviated word does inflect - "1 dni temu" is wrong Polish - so days
 * carries its own dzień/dni/dni/dnia rather than copying the other two units'
 * one-form-fits-all shape.
 */

return [
    'RelativeTime' => [
        'justNow' => 'przed chwilą',
        'minutes' => [
            'one' => '{count} min temu',
            'few' => '{count} min temu',
            'many' => '{count} min temu',
            'other' => '{count} min temu',
        ],
        'hours' => [
            'one' => '{count} godz. temu',
            'few' => '{count} godz. temu',
            'many' => '{count} godz. temu',
            'other' => '{count} godz. temu',
        ],
        'days' => [
            'one' => '{count} dzień temu',
            'few' => '{count} dni temu',
            'many' => '{count} dni temu',
            'other' => '{count} dnia temu',
        ],
    ],
];
