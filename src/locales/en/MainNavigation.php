<?php

declare(strict_types=1);

/**
 * The navigation, in English - on every page there is, which is why it reads
 * as the last English left on a site somebody has otherwise translated.
 *
 * Every one of these is a link's own text, which is a translatable string like
 * any other. The URLs beside them are not: a route is not words, and /tags/
 * stays /tags/ in every language.
 */

return [
    'MainNavigation' => [
        // The control is the hidden checkbox the hamburger flips, so this is
        // the only name a screen reader ever hears for the menu.
        'toggleLabel' => 'Menu',
        // {name} rather than concatenation: whose account this is may not come
        // last in the phrase, and some languages would not put it in one.
        'loggedInAs' => 'Logged In As {name}',
        'friendsFeed' => 'Friends Feed',
        'relayFeed' => 'Relay Feed',
        'users' => 'Users',
        'tags' => 'Tags',
        'topics' => 'Topics',
        'map' => 'Map',
        'locations' => 'Locations',
        'search' => 'Search',
        'messages' => 'Messages',
        'bookmarks' => 'Bookmarks',
        'help' => 'Help',
        'about' => 'About',
        'login' => 'Log In',
        'signup' => 'Sign Up',
        'friends' => 'Friends',
        'drafts' => 'Drafts & Scheduled',
        'userSettings' => 'User Settings',
        'modSettings' => 'Mod Settings',
        'adminSettings' => 'Admin Settings',
    ],

    'LogoutForm' => [
        'submit' => 'Log Out',
    ],
];
