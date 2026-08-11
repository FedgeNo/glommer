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
        'label' => 'Insert Emoji',
    ],

    'LinkImagePreview' => [
        'alt' => 'Link preview image',
    ],

    'LinkImageRemoveButton' => [
        'label' => 'Remove Image',
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
        'cumulativeMode' => 'Up to Then',
        'windowMode' => 'Just Then',
        'rangeLabel' => 'Show Posts up to a Date',
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
        'remove' => 'Remove Bookmark',
        'add' => 'Bookmark',
    ],

    'PostRepostButton' => [
        'undo' => 'Undo Repost',
        'repost' => 'Repost',
    ],

    'QuotedPost' => [
        'viewLink' => 'View the Quoted Post',
    ],

    'ReceivedFriendRequestSection' => [
        'heading' => 'Pending Requests',
    ],

    'ScrollToTopButton' => [
        'label' => 'Scroll to Top',
    ],

    'SearchClearButton' => [
        'label' => 'Clear Search',
    ],

    'SitePolicyLinks' => [
        'terms' => 'Terms of Service',
        'privacy' => 'Privacy Policy',
    ],

    'SkipLink' => [
        'label' => 'Skip to Content',
    ],

    'TopicHeading' => [
        'searchLink' => 'Search for This',
        // Shown in place of the feed under a topic nothing currently mentions.
        'noPosts' => 'No posts mention this right now.',
    ],

    'PopularEntityList' => [
        'emptyNotice' => 'Nothing of this kind has been written about yet.',
    ],

    'TrendingEntitySection' => [
        'heading' => 'Trending',
    ],
];
