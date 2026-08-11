<?php

declare(strict_types=1);

/**
 * How a date is written, in Spanish - "11 de agosto de 2026", on a 24-hour
 * clock. Month names are lowercase in Spanish, which is why they are written
 * that way here rather than looking like an oversight.
 */

return [
    'DateFormat' => [
        'months' => [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ],
        'shortMonths' => [
            1 => 'ene',
            2 => 'feb',
            3 => 'mar',
            4 => 'abr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'sept',
            10 => 'oct',
            11 => 'nov',
            12 => 'dic',
        ],
        'long' => '{day} de {month} de {year}',
        // The short form drops the second "de", the way a compact Spanish date
        // does: "11 ago 2026".
        'short' => '{day} {month} {year}',
        'time' => '{hour}:{minute}',
        'dateAndTime' => '{date} {time}',
        'clock' => 24,
    ],
];
