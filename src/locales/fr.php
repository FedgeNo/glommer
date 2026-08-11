<?php

declare(strict_types=1);

/**
 * French. Anything not written here falls back to src/locales/en.php.
 *
 * French counts zero the way it counts one - "0 vote" and "1 vote" take the
 * same singular form, and the plural starts only at two. So the rule below
 * differs from English's at the boundary, not just in name, and every counted
 * phrasing keeps {count} in its "one" form rather than hardcoding "1": the
 * callers substitute it unconditionally, and "0 vote" would otherwise print
 * as "1 vote".
 */

return [
    Strings::PLURAL_RULE => static fn (int $count): string => $count === 0 || $count === 1 ? 'one' : 'other',

    'PollOptionVotes' => [
        'votes' => ['one' => '{count} vote', 'other' => '{count} votes'],
    ],

    'LoginPrompt' => [
        'reply' => ['before' => '', 'link' => 'Connectez-vous', 'after' => ' pour répondre.'],
        'post' => ['before' => '', 'link' => 'Connectez-vous', 'after' => ' pour publier.'],
    ],
];
