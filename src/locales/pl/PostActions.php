<?php

declare(strict_types=1);

/**
 * Polish for the controls around a post. See src/locales/en/PostActions.php
 * for what each of these is.
 */

return [
    'PostLikeButton' => [
        'like' => 'Polub',
        'unlike' => 'Cofnij polubienie',
    ],

    'PostPinButton' => [
        'pin' => 'Przypnij',
        'unpin' => 'Odepnij',
    ],

    'PostQuoteButton' => [
        'name' => 'Cytuj',
    ],

    'PostDeleteButton' => [
        'name' => 'Usuń',
    ],

    'PostEditButton' => [
        'name' => 'Edytuj',
    ],

    'ReportButton' => [
        'name' => 'Zgłoś',
    ],

    'PostActionBar' => [
        'reply' => 'Odpowiedz',
        // "odpowiedź" happens to spell its nominative and genitive plural the
        // same way, so few and many read alike here - not a copy-paste, both
        // were checked against the noun's own declension table.
        'replies' => [
            'one' => 'Odpowiedź ({count})',
            'few' => 'Odpowiedzi ({count})',
            'many' => 'Odpowiedzi ({count})',
            'other' => 'Odpowiedzi ({count})',
        ],
    ],

    'PostEditedMarker' => [
        'label' => '(edytowano)',
    ],

    'RepliesHeading' => [
        'heading' => 'Odpowiedzi',
    ],

    'PollVoteButton' => [
        'name' => 'Głosuj',
    ],
];
