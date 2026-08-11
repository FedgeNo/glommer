<?php

declare(strict_types=1);

/**
 * The controls around a post, in English - the action row under it, the marks
 * on it, and the heading over its replies.
 *
 * Most of these are one word on a glyph-only button, which the reader never
 * sees: it is the aria-label a screen reader announces and the tooltip a mouse
 * uncovers. A one-word label is as untranslatable as a paragraph if the class
 * is the thing saying it.
 */

return [
    'PostLikeButton' => [
        'like' => 'Like',
        'unlike' => 'Unlike',
    ],

    'PostPinButton' => [
        'pin' => 'Pin',
        'unpin' => 'Unpin',
    ],

    'PostQuoteButton' => [
        'name' => 'Quote',
    ],

    'PostDeleteButton' => [
        'name' => 'Delete',
    ],

    'PostEditButton' => [
        'name' => 'Edit',
    ],

    'ReportButton' => [
        'name' => 'Report',
    ],

    'PostActionBar' => [
        // Two entries rather than one counted phrase with a "zero" form: CLDR
        // gives English no zero category, and this is not the same sentence
        // with a different number in it anyway - with none written yet the
        // control invites you to write one, and after that it counts them.
        'reply' => 'Reply',
        'replies' => ['one' => 'Replies ({count})', 'other' => 'Replies ({count})'],
    ],

    'PostEditedMarker' => [
        'label' => '(edited)',
    ],

    'RepliesHeading' => [
        'heading' => 'Replies',
    ],

    'PollVoteButton' => [
        'name' => 'Vote',
    ],
];
