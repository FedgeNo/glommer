<?php

declare(strict_types=1);

/**
 * How long ago something happened, in French.
 *
 * French counts zero as one, so its "one" form has to carry {count} rather than
 * spelling a numeral - see src/locales/fr.php. These all carry it anyway, since
 * the figure is the whole point of the phrase.
 */

return [
    'RelativeTime' => [
        'justNow' => 'à l\'instant',
        // "il y a" leads, where English's "ago" trails.
        'minutes' => ['one' => 'il y a {count} min', 'other' => 'il y a {count} min'],
        'hours' => ['one' => 'il y a {count} h', 'other' => 'il y a {count} h'],
        'days' => ['one' => 'il y a {count} j', 'other' => 'il y a {count} j'],
    ],
];
