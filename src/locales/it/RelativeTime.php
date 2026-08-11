<?php

declare(strict_types=1);

/** How long ago something happened, in Italian. See
 *  src/locales/en/RelativeTime.php for what this fragment covers. */

return [
    'RelativeTime' => [
        'justNow' => 'adesso',
        // Italian abbreviates the same way for one as for many, so both forms
        // are written alike - same reasoning English gives for its own
        // identical pair, and "fa" trails the number the way "ago" does.
        'minutes' => ['one' => '{count}m fa', 'other' => '{count}m fa'],
        'hours' => ['one' => '{count}h fa', 'other' => '{count}h fa'],
        'days' => ['one' => '{count}g fa', 'other' => '{count}g fa'],
    ],
];
