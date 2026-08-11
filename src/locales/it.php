<?php

declare(strict_types=1);

/**
 * Italian. Anything not written here falls back to src/locales/en.php.
 *
 * Italian counts the way English does - "1 voto" takes the singular and
 * everything else, including zero, takes the plural - so the rule below only
 * needs the same two categories, written out rather than left to the
 * fallback so that adding a language with three is an edit to that
 * language's file and nowhere else.
 */

return [
    Strings::PLURAL_RULE => static fn (int $count): string => $count === 1 ? 'one' : 'other',

    'PollOptionVotes' => [
        'votes' => ['one' => '1 voto', 'other' => '{count} voti'],
    ],

    'LoginPrompt' => [
        'reply' => ['before' => '', 'link' => 'Accedi', 'after' => ' per rispondere.'],
        'post' => ['before' => '', 'link' => 'Accedi', 'after' => ' per pubblicare.'],
    ],
];
