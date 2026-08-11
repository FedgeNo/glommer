<?php

declare(strict_types=1);

/**
 * Spanish. Anything not written here falls back to src/locales/en.php.
 */

return [
    // Two forms, the same as English - written out rather than left to the
    // fallback so that adding a language with three is an edit to that
    // language's file and nowhere else.
    Strings::PLURAL_RULE => static fn (int $count): string => $count === 1 ? 'one' : 'other',

    'PollOptionVotes' => [
        'votes' => ['one' => '1 voto', 'other' => '{count} votos'],
    ],

    'LoginPrompt' => [
        'reply' => ['before' => '', 'link' => 'Inicia sesión', 'after' => ' para responder.'],
        'post' => ['before' => '', 'link' => 'Inicia sesión', 'after' => ' para publicar.'],
    ],
];
