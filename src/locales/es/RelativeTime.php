<?php

declare(strict_types=1);

/** How long ago something happened, in Spanish. */

return [
    'RelativeTime' => [
        'justNow' => 'ahora mismo',
        // "hace" leads, where English's "ago" trails.
        'minutes' => ['one' => 'hace {count} min', 'other' => 'hace {count} min'],
        'hours' => ['one' => 'hace {count} h', 'other' => 'hace {count} h'],
        'days' => ['one' => 'hace {count} d', 'other' => 'hace {count} d'],
    ],
];
