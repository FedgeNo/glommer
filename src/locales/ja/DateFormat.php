<?php

declare(strict_types=1);

/**
 * How a date is written, in Japanese - year first, each part closed off with
 * its own counter word ("2026年8月11日"), on a 24-hour clock. Japanese names a
 * month by its number rather than a separate word, so 'months' and
 * 'shortMonths' are both just the numerals 1-12 and 'long'/'short' are the
 * same pattern. With no 12-hour clock there is no meridiem to say, so 'time'
 * has no {meridiem} in it and this file carries no 'am'/'pm' entries at all.
 */

return [
    'DateFormat' => [
        'months' => [
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
            10 => '10',
            11 => '11',
            12 => '12',
        ],
        'shortMonths' => [
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
            10 => '10',
            11 => '11',
            12 => '12',
        ],
        'long' => '{year}年{month}月{day}日',
        'short' => '{year}年{month}月{day}日',
        'time' => '{hour}時{minute}分',
        'dateAndTime' => '{date} {time}',
        'clock' => 24,
    ],
];
