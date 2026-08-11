<?php

declare(strict_types=1);

/**
 * How a date is written, in French - "11 août 2026", on a 24-hour clock. Month
 * names are lowercase in French, which is why they are written that way here
 * rather than looking like an oversight.
 */

return [
    'DateFormat' => [
        'months' => [
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
        ],
        'shortMonths' => [
            1 => 'janv.',
            2 => 'févr.',
            3 => 'mars',
            4 => 'avr.',
            5 => 'mai',
            6 => 'juin',
            7 => 'juil.',
            8 => 'août',
            9 => 'sept.',
            10 => 'oct.',
            11 => 'nov.',
            12 => 'déc.',
        ],
        'long' => '{day} {month} {year}',
        'short' => '{day} {month} {year}',
        'time' => '{hour}:{minute}',
        'dateAndTime' => '{date} {time}',
        'clock' => 24,
    ],
];
