<?php

declare(strict_types=1);

/**
 * Portuguese (European). Anything not written here falls back to
 * src/locales/en.php.
 */

return [
    // Portuguese treats a count of zero the same as one, unlike English.
    Strings::PLURAL_RULE => static fn (int $count): string => $count === 0 || $count === 1 ? 'one' : 'other',

    'PollOptionVotes' => [
        'votes' => ['one' => '{count} voto', 'other' => '{count} votos'],
    ],

    'LoginPrompt' => [
        'reply' => ['before' => '', 'link' => 'Iniciar sessão', 'after' => ' para responder.'],
        'post' => ['before' => '', 'link' => 'Iniciar sessão', 'after' => ' para publicar.'],
    ],
];
