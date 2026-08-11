<?php

declare(strict_types=1);

/** How long ago something happened, in Dutch. */

return [
    'RelativeTime' => [
        'justNow' => 'zojuist',
        // Dutch marks "ago" with "geleden" after the figure, same order as
        // English's own "ago" - and, like English, abbreviates the same way
        // for one as for many.
        'minutes' => ['one' => '{count}m geleden', 'other' => '{count}m geleden'],
        'hours' => ['one' => '{count}u geleden', 'other' => '{count}u geleden'],
        'days' => ['one' => '{count}d geleden', 'other' => '{count}d geleden'],
    ],
];
