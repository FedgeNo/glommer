<?php

declare(strict_types=1);

/**
 * The words a page's browser tab carries, and the meta/Open Graph description
 * search engines and link previews read - one key per route, both rendered by
 * PageTitle (which the title also reaches as the <h1> at the top of every
 * page, not only the tab). A route with only one of the two - most have just
 * a title - carries only that key.
 *
 * A handful of titles are left out on purpose: a hashtag, a place name, a
 * trending entity's own name, and a Help article's title are all data rather
 * than English sentences, so the page script keeps them inline instead of
 * routing them through here.
 */

return [
    'PageTitle' => [
        'about' => 'About',
        'adminBanned' => 'Banned Users',
        'adminModSettings' => 'Mod Settings',
        'adminReports' => 'Reports',
        'adminSettings' => 'Admin Settings',
        'adminTests' => 'Tests',
        'authGoogleCallbackAccountDeleted' => 'Account Deleted',
        'authGoogleCallbackDeleteAccount' => 'Delete Account',
        'authGoogleCallbackLogin' => 'Log In',
        'bookmarks' => 'Bookmarks',
        'checkInbox' => 'Check Your Inbox',
        'drafts' => 'Drafts & Scheduled',
        'draftsEditDraft' => 'Edit Draft',
        'draftsEditScheduled' => 'Edit Scheduled Post',
        'forgotPassword' => 'Forgot Password',
        'friendsFeed' => 'Friends Feed',
        'help' => 'Help',
        'helpDescription' => 'Guides and answers for using the site.',
        'locations' => 'Locations',
        'locationsDescription' => 'Posts from the places closest to you.',
        'locationsPlaceDescription' => 'Posts near {place}.',
        'login' => 'Log In',
        'loginVerificationCode' => 'Verification Code',
        'map' => 'Map',
        'mapDescription' => 'A map of posts from around the world - find people and things near you.',
        'messages' => 'Messages',
        'messagesWithUser' => 'Messages with {name}',
        'notifications' => 'Notifications',
        'privacy' => 'Privacy Policy',
        'quote' => 'Quote',
        'relayFeed' => 'Relay Feed',
        'relayFeedDescription' => 'Public posts arriving from the relays this server subscribes to.',
        'resetPassword' => 'Reset Password',
        'revertEmail' => 'Revert Email Change',
        'search' => 'Search',
        'signup' => 'Sign Up',
        'tags' => 'Tags',
        'tagsDescription' => 'Browse trending and popular hashtags on {siteTitle}.',
        // {tag} arrives with its leading # already attached (see tags.php) -
        // a hashtag is the same word in every language, so it travels as one
        // token rather than being rebuilt from a bare slug plus punctuation
        // each locale would have to place correctly.
        'tagsTagDescription' => 'Posts tagged {tag} on {siteTitle}.',
        'terms' => 'Terms of Service',
        'topics' => 'Topics',
        'topicsDescription' => 'What people are talking about on {siteTitle} right now.',
        // {typeLabel}, {typePlural} and {entityTitle} come from EntityType and
        // the trending entity itself (topics.php) - English-only, outside this
        // file's reach, so a translation still frames an untranslated noun.
        'topicsEntityDescription' => '{typeLabel} - posts mentioning {entityTitle} on {siteTitle}.',
        'topicsTypeDescription' => '{typePlural} people are talking about on {siteTitle} right now.',
        'unsubscribe' => 'Email Digests',
        // Mirrors Post's pageTitleByAuthor ('{name}\'s Post') - a language
        // with no possessive 's rebuilds the whole phrase around {name}
        // rather than keeping the apostrophe construction.
        'userFriends' => '{name}\'s Friends',
        'userFriendsDescription' => 'Friends of {name} on {siteTitle}',
        'userSettings' => 'User Settings',
        'users' => 'Users',
        'verifyEmail' => 'Verify Email',
    ],
];
