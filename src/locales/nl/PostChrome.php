<?php

declare(strict_types=1);

/**
 * Dutch for the post card, the small controls around it, and the page
 * furniture that doesn't belong to any one feature. See
 * src/locales/en/PostChrome.php for the source and the shape each entry is
 * built to.
 */

return [
    'EmojiPickerTriggerButton' => [
        'label' => 'Emoji invoegen',
    ],

    'LinkImagePreview' => [
        'alt' => 'Voorvertoningsafbeelding van link',
    ],

    'LinkImageRemoveButton' => [
        'label' => 'Afbeelding verwijderen',
    ],

    'LinkItem' => [
        'alt' => 'Voorvertoningsafbeelding van link',
    ],

    'MapScrubber' => [
        'play' => 'Afspelen',
        'pause' => 'Pauzeren',
        'cumulativeMode' => 'Tot dan',
        'windowMode' => 'Alleen dan',
        'rangeLabel' => 'Posts tonen tot een datum',
        'cumulativeLabel' => ['one' => 'Gepost tot {date} — {count} post', 'other' => 'Gepost tot {date} — {count} posts'],
        'windowLabel' => ['one' => 'Gepost rond {date} — {count} post', 'other' => 'Gepost rond {date} — {count} posts'],
    ],

    'Post' => [
        'pageTitleByAuthor' => 'Post van {name}',
        'pageTitleUntitled' => 'Post',
        'imageAltByAuthor' => 'Foto gepost door {name}',
        'imageAltUntitled' => 'Foto',
    ],

    'PostBookmarkButton' => [
        'remove' => 'Bladwijzer verwijderen',
        'add' => 'Bladwijzer toevoegen',
    ],

    'PostRepostButton' => [
        'undo' => 'Repost ongedaan maken',
        'repost' => 'Repost',
    ],

    'QuotedPost' => [
        'viewLink' => 'Bekijk de geciteerde post',
    ],

    'ReceivedFriendRequestSection' => [
        'heading' => 'Openstaande verzoeken',
    ],

    'ScrollToTopButton' => [
        'label' => 'Naar boven scrollen',
    ],

    'SearchClearButton' => [
        'label' => 'Zoekopdracht wissen',
    ],

    'SitePolicyLinks' => [
        'terms' => 'Gebruiksvoorwaarden',
        'privacy' => 'Privacybeleid',
    ],

    'SkipLink' => [
        'label' => 'Naar inhoud springen',
    ],

    'TopicHeading' => [
        'searchLink' => 'Hierop zoeken',
        'noPosts' => 'Op dit moment noemt geen enkele post dit.',
    ],

    'PopularEntityList' => [
        'emptyNotice' => 'Hier is nog niets van dit soort over geschreven.',
    ],

    'TrendingEntitySection' => [
        // Kept as "Trending" rather than translated: the loanword is already
        // the term Dutch social-media readers know for this feature, and
        // WelcomeBanner's own paragraph about it points here by that same
        // word.
        'heading' => 'Trending',
    ],
];
