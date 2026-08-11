<?php

declare(strict_types=1);

/**
 * How a date is written, in German - "11. August 2026", on a 24-hour clock.
 */

return [
    'DateFormat' => [
        'months' => [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember',
        ],
        'shortMonths' => [
            1 => 'Jan.',
            2 => 'Feb.',
            3 => 'März',
            4 => 'Apr.',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Aug.',
            9 => 'Sept.',
            10 => 'Okt.',
            11 => 'Nov.',
            12 => 'Dez.',
        ],
        'long' => '{day}. {month} {year}',
        'short' => '{day}. {month} {year}',
        // No {meridiem}: a German clock runs to twenty-four and has no word for
        // which half of the day it is.
        'time' => '{hour}:{minute}',
        'dateAndTime' => '{date} {time}',
        'clock' => 24,
    ],
];
