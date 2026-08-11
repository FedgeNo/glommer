<?php

declare(strict_types=1);

/**
 * How a date is written, in English.
 *
 * Three separate decisions, kept apart because languages make them
 * independently: what the months are called, how the pieces of a date are
 * arranged, and how a clock is read.
 *
 * The arrangement is a phrasing with {day}, {month} and {year} in it, so a
 * language puts them in its own order and joins them with its own words -
 * English "August 11, 2026", German "11. August 2026", Spanish
 * "11 de agosto de 2026".
 *
 * 'clock' is 12 or 24. On 24 the hour is zero-padded and {meridiem} has nothing
 * to say, so a language on that clock simply leaves it out of its phrasing.
 */

return [
    'DateFormat' => [
        // Keyed 1-12 rather than as a bare list, so a locale that writes only
        // some of them merges by month rather than by position.
        'months' => [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ],
        'shortMonths' => [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        ],
        'long' => '{month} {day}, {year}',
        'short' => '{month} {day}, {year}',
        'time' => '{hour}:{minute} {meridiem}',
        'dateAndTime' => '{date} {time}',
        'am' => 'AM',
        'pm' => 'PM',
        'clock' => 12,
    ],
];
