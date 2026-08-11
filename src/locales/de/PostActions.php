<?php

declare(strict_types=1);

/**
 * German for the controls around a post - the action row under it, the marks
 * on it, and the heading over its replies. See src/locales/en/PostActions.php
 * for the source and the shape each entry is built to.
 */

return [
    'PostLikeButton' => [
        'like' => 'Gefällt mir',
        'unlike' => 'Gefällt mir nicht mehr',
    ],

    'PostPinButton' => [
        'pin' => 'Anheften',
        'unpin' => 'Loslösen',
    ],

    'PostQuoteButton' => [
        'name' => 'Zitieren',
    ],

    'PostDeleteButton' => [
        'name' => 'Löschen',
    ],

    'PostEditButton' => [
        'name' => 'Bearbeiten',
    ],

    'ReportButton' => [
        'name' => 'Melden',
    ],

    'PostActionBar' => [
        'reply' => 'Antworten',
        // German inflects the noun by count, unlike English's invariant pair
        // - same pattern as MapScrubber.php's cumulativeLabel/windowLabel.
        'replies' => ['one' => 'Antwort ({count})', 'other' => 'Antworten ({count})'],
    ],

    'PostEditedMarker' => [
        'label' => '(bearbeitet)',
    ],

    'RepliesHeading' => [
        'heading' => 'Antworten',
    ],

    'PollVoteButton' => [
        'name' => 'Abstimmen',
    ],
];
