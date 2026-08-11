<?php

declare(strict_types=1);

/**
 * The controls around a post, in Portuguese - the action row under it, the
 * marks on it, and the heading over its replies. See
 * src/locales/en/PostActions.php for what these classes are.
 */

return [
    'PostLikeButton' => [
        'like' => 'Gostar',
        'unlike' => 'Deixar de gostar',
    ],

    'PostPinButton' => [
        'pin' => 'Fixar',
        'unpin' => 'Desafixar',
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
        // Both forms identical, matching the English this replaces: a label
        // with a count in parentheses, not a sentence that agrees with the
        // number. That also makes it safe if the count is ever 0 here -
        // 'Resposta ({count})' would print "Resposta (0)".
        'reply' => 'Responder',
        'replies' => ['one' => 'Respostas ({count})', 'other' => 'Respostas ({count})'],
    ],

    'PostEditedMarker' => [
        // Agrees with "publicação" (feminine) - see ReportCard.targetTypes -
        // since this marker is only ever shown on a post.
        'label' => '(editada)',
    ],

    'RepliesHeading' => [
        'heading' => 'Respostas',
    ],

    'PollVoteButton' => [
        'name' => 'Votar',
    ],
];
