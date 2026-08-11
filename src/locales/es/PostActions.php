<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/PostActions.php for what this fragment covers.
 */

return [
    'PostLikeButton' => [
        'like' => 'Me gusta',
        'unlike' => 'Ya no me gusta',
    ],

    'PostPinButton' => [
        'pin' => 'Fijar',
        'unpin' => 'Dejar de fijar',
    ],

    'PostQuoteButton' => [
        'name' => 'Citar',
    ],

    'PostDeleteButton' => [
        'name' => 'Eliminar',
    ],

    'PostEditButton' => [
        'name' => 'Editar',
    ],

    'ReportButton' => [
        'name' => 'Denunciar',
    ],

    'PostActionBar' => [
        'reply' => 'Responder',
        // Unlike English, "one" and "other" genuinely differ here: Spanish's
        // "one" fires only at exactly 1, where "Respuestas (1)" would be
        // wrong Spanish - "Respuesta (1)" is what the singular asks for.
        'replies' => ['one' => 'Respuesta ({count})', 'other' => 'Respuestas ({count})'],
    ],

    'PostEditedMarker' => [
        'label' => '(editado)',
    ],

    'RepliesHeading' => [
        'heading' => 'Respuestas',
    ],

    'PollVoteButton' => [
        'name' => 'Votar',
    ],
];
