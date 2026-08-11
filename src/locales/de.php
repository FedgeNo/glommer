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
    'LoginPrompt' => [
        'reply' => ['before' => 'Zum Antworten bitte ', 'link' => 'anmelden', 'after' => '.'],
        'post' => ['before' => 'Zum Posten bitte ', 'link' => 'anmelden', 'after' => '.'],
    ],
];
