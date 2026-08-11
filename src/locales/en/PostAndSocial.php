<?php

declare(strict_types=1);

/**
 * The words the composer, poll and feed-context classes say, in English.
 *
 * A fragment of src/locales/en.php - see that file for what a fragment is and
 * why converting these classes doesn't have to touch the ones anybody else is
 * converting at the same time.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => 'See ', 'link' => 'more locations', 'after' => ''],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'Posts near you',
        'description' => 'This shows the posts closest to a point - wherever there is activity, however far away it happens to be. Share your location to start from where you are, or pick a spot on the map instead.',
        'useMyLocation' => 'Use my location',
        'pickOnMap' => 'Pick on the map',
        'searchPlaceholder' => 'Or type a place name…',
        'searchLabel' => 'Search for a place',
        // Read only by the client twin (NearbyLocationPrompt.js): the button's
        // text mid-lookup, and the two failure toasts geolocation can produce.
        // Kept under this same key, same as every other class whose words
        // cross the PHP/JS boundary - Strings.for($class) is the one lookup
        // both renderers share.
        'locating' => 'Locating…',
        'noGeolocation' => 'Your browser can\'t share a location.',
        'locationError' => 'Could not get your location. Check your browser\'s location permission.',
    ],

    'PollDeadline' => [
        'final' => 'Final result',
        'closes' => ['before' => 'Closes ', 'after' => ''],
        // "in " lives inside each unit's own phrasing rather than as a shared
        // prefix, so a language can decide per unit how the count and the
        // word for "in" combine - it is not always the same move for days as
        // it is for minutes.
        'days' => ['one' => 'in {count} day', 'other' => 'in {count} days'],
        'hours' => ['one' => 'in {count} hour', 'other' => 'in {count} hours'],
        'minutes' => ['one' => 'in {count} minute', 'other' => 'in {count} minutes'],
        'underMinute' => 'in under a minute',
    ],

    'PollTally' => [
        // Trailing space: this sits directly before PollDeadline's own words
        // with nothing else between them.
        'voters' => ['one' => '1 person voted ', 'other' => '{count} people voted '],
    ],

    'PostComposer' => [
        'prompt' => ['before' => '', 'link' => 'Log in', 'after' => ' to post.'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => '', 'link' => 'Log in', 'after' => ' to reply.'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => '', 'after' => ' reposted'],
    ],

    'ThreadContext' => [
        'response' => ['before' => 'In response to ', 'after' => ''],
        // What the link says when the parent has no title and no text to
        // stand in for one - resolved here rather than where parentLabel is
        // fetched, since that runs once per batch and ships to the client
        // as data; the words for "nothing to call it" belong to whichever
        // side renders, English here and Post.js's own fallback there.
        'untitled' => 'this post',
        'jumpToStart' => 'Jump to start',
    ],

    'TopicSummaryCard' => [
        'label' => 'AI-generated summary',
    ],

    'WelcomeBanner' => [
        'heading' => ['before' => 'Welcome to ', 'after' => ''],
        // What somebody needs to know to use the place, shortest first. Each
        // is one idea, because a wall of text at the top of the feed is a
        // thing people close without reading.
        'paragraphs' => [
            'Write something in the box below and it goes out to your feed. Anyone can reply, and a reply is just a post with a parent, so conversations nest as deep as they need to.',
            'Add people as friends and their posts join your feed. The Global feed shows everything written here, which is the place to find somebody to add.',
            'This site is part of the Fediverse: you can follow accounts on Mastodon and other servers by their full handle, and what you post reaches the people who follow you there. Search for a handle like @someone@example.social and this server will go and find them.',
            'Tag a post with #hashtags and it turns up on that tag\'s page, and in Trending if enough people are writing about it.',
            'Messages between members are end-to-end encrypted - the server stores ciphertext it cannot read. Turn that on in Settings.',
            'You do not have to post straight away: save a draft, or set a time and it publishes itself. Both live under Drafts & Scheduled.',
        ],
        'more' => ['before' => 'There is more in ', 'link' => 'the help pages', 'after' => ', including how to move an account here from elsewhere.'],
        'dontShowAgain' => 'Don\'t show this again',
    ],
];
