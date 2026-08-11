<?php

declare(strict_types=1);

/**
 * How long ago something happened, in Portuguese.
 *
 * Portuguese counts zero as one, so its "one" form has to carry {count} rather
 * than spelling a numeral - see src/locales/pt.php. These all carry it anyway,
 * since the figure is the whole point of the phrase.
 */

return [
    'RelativeTime' => [
        'justNow' => 'agora mesmo',
        // "há" leads, where English's "ago" trails.
        'minutes' => ['one' => 'há {count} min', 'other' => 'há {count} min'],
        'hours' => ['one' => 'há {count} h', 'other' => 'há {count} h'],
        'days' => ['one' => 'há {count} d', 'other' => 'há {count} d'],
    ],
];
