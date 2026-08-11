<?php

declare(strict_types=1);

/**
 * How a date is written, in Dutch - "11 augustus 2026", on a 24-hour clock.
 * Month names are lowercase in Dutch, which is why they are written that way
 * here rather than looking like an oversight.
 */

return [
    'DateFormat' => [
        'months' => [
            1 => 'januari',
            2 => 'februari',
            3 => 'maart',
            4 => 'april',
            5 => 'mei',
            6 => 'juni',
            7 => 'juli',
            8 => 'augustus',
            9 => 'september',
            10 => 'oktober',
            11 => 'november',
            12 => 'december',
        ],
        'shortMonths' => [
            1 => 'jan.',
            2 => 'feb.',
            3 => 'mrt.',
            4 => 'apr.',
            5 => 'mei',
            6 => 'jun.',
            7 => 'jul.',
            8 => 'aug.',
            9 => 'sep.',
            10 => 'okt.',
            11 => 'nov.',
            12 => 'dec.',
        ],
        'long' => '{day} {month} {year}',
        'short' => '{day} {month} {year}',
        // No {meridiem}: a Dutch clock runs to twenty-four and has no word for
        // which half of the day it is.
        'time' => '{hour}:{minute}',
        'dateAndTime' => '{date} {time}',
        'clock' => 24,
    ],
];
