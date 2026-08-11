<?php

declare(strict_types=1);

/**
 * How a date is written, in Polish - "11 sierpnia 2026", on a 24-hour clock.
 * Month names are lowercase, and written in the genitive form a date takes
 * ("11 sierpnia", not "11 styczeń") rather than the nominative one a calendar
 * page or a month-only label would use - this table only ever feeds a whole
 * date, so the genitive is the only form written here.
 */

return [
    'DateFormat' => [
        'months' => [
            1 => 'stycznia',
            2 => 'lutego',
            3 => 'marca',
            4 => 'kwietnia',
            5 => 'maja',
            6 => 'czerwca',
            7 => 'lipca',
            8 => 'sierpnia',
            9 => 'września',
            10 => 'października',
            11 => 'listopada',
            12 => 'grudnia',
        ],
        'shortMonths' => [
            1 => 'sty',
            2 => 'lut',
            3 => 'mar',
            4 => 'kwi',
            5 => 'maj',
            6 => 'cze',
            7 => 'lip',
            8 => 'sie',
            9 => 'wrz',
            10 => 'paź',
            11 => 'lis',
            12 => 'gru',
        ],
        'long' => '{day} {month} {year}',
        'short' => '{day} {month} {year}',
        // No {meridiem}: a Polish clock runs to twenty-four and has no word for
        // which half of the day it is.
        'time' => '{hour}:{minute}',
        'dateAndTime' => '{date} {time}',
        'clock' => 24,
    ],
];
