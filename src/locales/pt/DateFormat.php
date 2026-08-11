<?php

declare(strict_types=1);

/**
 * How a date is written, in Portuguese - "11 de agosto de 2026", on a 24-hour
 * clock. Month names are lowercase in Portuguese, which is why they are written
 * that way here rather than looking like an oversight.
 */

return [
    'DateFormat' => [
        'months' => [
            1 => 'janeiro',
            2 => 'fevereiro',
            3 => 'março',
            4 => 'abril',
            5 => 'maio',
            6 => 'junho',
            7 => 'julho',
            8 => 'agosto',
            9 => 'setembro',
            10 => 'outubro',
            11 => 'novembro',
            12 => 'dezembro',
        ],
        'shortMonths' => [
            1 => 'jan',
            2 => 'fev',
            3 => 'mar',
            4 => 'abr',
            5 => 'mai',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'set',
            10 => 'out',
            11 => 'nov',
            12 => 'dez',
        ],
        'long' => '{day} de {month} de {year}',
        'short' => '{day} {month} {year}',
        'time' => '{hour}:{minute}',
        'dateAndTime' => '{date} {time}',
        'clock' => 24,
    ],
];
