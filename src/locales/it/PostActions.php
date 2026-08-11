<?php

declare(strict_types=1);

/**
 * Italian for the controls around a post. See src/locales/en/PostActions.php
 * for what this fragment covers.
 */

return [
    'PostLikeButton' => [
        'like' => 'Mi piace',
        'unlike' => 'Non mi piace più',
    ],

    'PostPinButton' => [
        'pin' => 'Fissa',
        'unpin' => 'Non fissare più',
    ],

    'PostQuoteButton' => [
        'name' => 'Cita',
    ],

    'PostDeleteButton' => [
        'name' => 'Elimina',
    ],

    'PostEditButton' => [
        'name' => 'Modifica',
    ],

    'ReportButton' => [
        'name' => 'Segnala',
    ],

    'PostActionBar' => [
        'reply' => 'Rispondi',
        // Italian's "one" fires only at exactly 1, the same boundary as
        // English - but unlike English the singular and plural noun genuinely
        // differ: "Risposta (1)" is what the singular asks for, not
        // "Risposte (1)".
        'replies' => ['one' => 'Risposta ({count})', 'other' => 'Risposte ({count})'],
    ],

    'PostEditedMarker' => [
        'label' => '(modificato)',
    ],

    'RepliesHeading' => [
        'heading' => 'Risposte',
    ],

    'PollVoteButton' => [
        'name' => 'Vota',
    ],
];
