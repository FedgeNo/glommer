<?php

declare(strict_types=1);

/**
 * German. Anything not written here falls back to src/locales/en.php.
 *
 * A worked example of why a sentence is a list of pieces: German puts the
 * reason first and ends on the verb, so the words move out of `after` and into
 * `before`, and the control's own label changes with them. Nothing in
 * LoginPrompt.php had to know that.
 */

return [
    // Two forms, the same as English - written out rather than left to the
    // fallback so that adding a language with three is an edit to that
    // language's file and nowhere else.
    Strings::PLURAL_RULE => static fn (int $count): string => $count === 1 ? 'one' : 'other',

    'PollOptionVotes' => [
        'votes' => ['one' => '1 Stimme', 'other' => '{count} Stimmen'],
    ],

    'LoginPrompt' => [
        'reply' => ['before' => 'Zum Antworten bitte ', 'link' => 'anmelden', 'after' => '.'],
        'post' => ['before' => 'Zum Posten bitte ', 'link' => 'anmelden', 'after' => '.'],
    ],
];
