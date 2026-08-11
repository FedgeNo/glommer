<?php

declare(strict_types=1);

/**
 * How a date is written, in Italian - "11 agosto 2026", on a 24-hour clock.
 * Month names are lowercase in Italian, which is why they are written that
 * way here rather than looking like an oversight. No {meridiem}: a 24-hour
 * clock has no word for which half of the day it is, so 'am'/'pm' are left
 * out rather than filled with something nothing ever reads.
 */

return [
    'DateFormat' => [
        'months' => [
            1 => 'gennaio',
            2 => 'febbraio',
            3 => 'marzo',
            4 => 'aprile',
            5 => 'maggio',
            6 => 'giugno',
            7 => 'luglio',
            8 => 'agosto',
            9 => 'settembre',
            10 => 'ottobre',
            11 => 'novembre',
            12 => 'dicembre',
        ],
        'shortMonths' => [
            1 => 'gen',
            2 => 'feb',
            3 => 'mar',
            4 => 'apr',
            5 => 'mag',
            6 => 'giu',
            7 => 'lug',
            8 => 'ago',
            9 => 'set',
            10 => 'ott',
            11 => 'nov',
            12 => 'dic',
        ],
        'long' => '{day} {month} {year}',
        'short' => '{day} {month} {year}',
        'time' => '{hour}:{minute}',
        'dateAndTime' => '{date} {time}',
        'clock' => 24,
    ],
];
