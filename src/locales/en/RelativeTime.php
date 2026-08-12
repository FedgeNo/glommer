<?php

declare(strict_types=1);

/**
 * How long ago something happened, in English.
 *
 * Only the client ever says these: RelativeTime::toDOM() renders an absolute
 * date, and RelativeTime.js replaces it with one of these the moment the page
 * loads and once a minute after that. They live in the shared table anyway,
 * since locales/*.js is that table written out - a separate file of words for
 * the client would be the drift this whole system exists to avoid.
 *
 * Counted rather than glued together from a number and a unit: the marker for
 * "ago" is a suffix in English and a prefix in most of the languages beside it,
 * so there is no arrangement of pieces the code could do on a language's behalf.
 */

return [
    'RelativeTime' => [
        'justNow' => 'just now',
        // English abbreviates the same way for one as for many, so both forms
        // are written alike. They are still both written: which counts share a
        // form is the language's answer to give, and a locale that needs four
        // of them inherits nothing useful from English having needed one.
        'minutes' => ['one' => '{count}m ago', 'other' => '{count}m ago'],
        'hours' => ['one' => '{count}h ago', 'other' => '{count}h ago'],
        'days' => ['one' => '{count}d ago', 'other' => '{count}d ago'],
    ],
];
