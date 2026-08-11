<?php

declare(strict_types=1);

/**
 * The words the post card says, the small controls around it, and the page
 * furniture that doesn't belong to any one feature - buttons, links and
 * headings scattered across otherwise unrelated pages.
 *
 * A fragment of src/locales/en.php - see that file for what a fragment is and
 * why converting these classes doesn't have to touch the ones anybody else is
 * converting at the same time.
 */

return [
    'EmojiPickerTriggerButton' => [
        'label' => 'Insert emoji',
    ],

    'LinkImagePreview' => [
        'alt' => 'Link preview image',
    ],

    'LinkImageRemoveButton' => [
        'label' => 'Remove image',
    ],

    'LinkItem' => [
        'alt' => 'Link preview image',
    ],

    'MapScrubber' => [
        'play' => 'Play',
        // Read only by the client twin (MapScrubber.js), which toggles this
        // button between the two - same convention as NearbyLocationPrompt's
        // JS-only keys.
        'pause' => 'Pause',
        'cumulativeMode' => 'Up to then',
        'windowMode' => 'Just then',
        'rangeLabel' => 'Show posts up to a date',
        // Also JS-only: the label over the map, rebuilt on every drag of the
        // handle. {date} is the browser's own locale-formatted date, dropped
        // in beside the count the same way {count} is.
        'cumulativeLabel' => ['one' => 'Posted up to {date} — {count} post', 'other' => 'Posted up to {date} — {count} posts'],
        'windowLabel' => ['one' => 'Posted around {date} — {count} post', 'other' => 'Posted around {date} — {count} posts'],
    ],

    'Post' => [
        // pageTitle() and imageAltText(): what stands in for a name or a
        // caption nobody wrote. {name} is substituted the way {count} is - a
        // plain value, not a control, so it takes no before/after pieces.
        'pageTitleByAuthor' => '{name}\'s Post',
        'pageTitleUntitled' => 'Post',
        'imageAltByAuthor' => 'Photo posted by {name}',
        'imageAltUntitled' => 'Photo',
    ],

    'PostBookmarkButton' => [
        'remove' => 'Remove bookmark',
        'add' => 'Bookmark',
    ],

    'PostRepostButton' => [
        'undo' => 'Undo repost',
        'repost' => 'Repost',
    ],

    'QuotedPost' => [
        'viewLink' => 'View the quoted post',
    ],

    'ReceivedFriendRequestSection' => [
        'heading' => 'Pending requests',
    ],

    'ScrollToTopButton' => [
        'label' => 'Scroll to top',
    ],

    'SearchClearButton' => [
        'label' => 'Clear search',
    ],

    'SitePolicyLinks' => [
        'terms' => 'Terms of Service',
        'privacy' => 'Privacy Policy',
    ],

    'SkipLink' => [
        'label' => 'Skip to content',
    ],

    'TopicHeading' => [
        'searchLink' => 'Search for this',
    ],
];
