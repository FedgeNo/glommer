<?php

declare(strict_types=1);

/**
 * German for the site navigation and the logout control. See
 * src/locales/en/MainNavigation.php for the source and the shape each entry
 * is built to.
 */

return [
    'MainNavigation' => [
        'toggleLabel' => 'Menü',
        'loggedInAs' => 'Angemeldet als {name}',
        'friendsFeed' => 'Freunde-Feed',
        'relayFeed' => 'Relay-Feed',
        'users' => 'Nutzer',
        'tags' => 'Tags',
        'topics' => 'Trends',
        'map' => 'Karte',
        'locations' => 'Orte',
        'search' => 'Suche',
        'messages' => 'Nachrichten',
        'bookmarks' => 'Lesezeichen',
        'help' => 'Hilfe',
        // Not the bare "Über" that AboutSettingsForm.legend uses - that sits
        // inside a fieldset whose own heading supplies the referent, while a
        // nav link stands alone and needs the object a German preposition
        // wants.
        'about' => 'Über uns',
        'login' => 'Anmelden',
        'signup' => 'Registrieren',
        'friends' => 'Freunde',
        'drafts' => 'Entwürfe & geplante Beiträge',
        'userSettings' => 'Kontoeinstellungen',
        // Spelt out rather than "Mod-Einstellungen": as a compound modifier
        // "Mod-" reads as Modifikation (game mods) at least as readily as
        // Moderator. Still ends in -einstellungen, because these three are one
        // named set - User, Mod and Admin Settings - and the odd one out reads
        // as a different kind of thing rather than the same page for moderators.
        'modSettings' => 'Moderationseinstellungen',
        'adminSettings' => 'Admin-Einstellungen',
    ],

    'LogoutForm' => [
        'submit' => 'Abmelden',
    ],
];
