<?php

declare(strict_types=1);

/** How long ago something happened, in German. */

return [
    'RelativeTime' => [
        'justNow' => 'gerade eben',
        // German marks "ago" with "vor" in front of the figure rather than a
        // word after it, which is why these are whole phrasings and not a unit
        // the code appends something to.
        'minutes' => ['one' => 'vor {count} Min.', 'other' => 'vor {count} Min.'],
        'hours' => ['one' => 'vor {count} Std.', 'other' => 'vor {count} Std.'],
        'days' => ['one' => 'vor {count} T.', 'other' => 'vor {count} T.'],
    ],
];
