<?php

declare(strict_types=1);

/**
 * German for the page titles and meta descriptions. These render as both the
 * browser tab <title> and the on-page <h1> at the top of every page (see
 * Page::assembleBody), so they read as headings, not just tab labels. See
 * src/locales/en/PageTitle.php for the source and the shape each entry is
 * built to.
 */

return [
    'PageTitle' => [
        'about' => 'Über uns',
        'adminBanned' => 'Gesperrte Nutzer',
        'adminModSettings' => 'Moderationseinstellungen',
        'adminReports' => 'Meldungen',
        'adminSettings' => 'Admin-Einstellungen',
        'adminTests' => 'Tests',
        'authGoogleCallbackAccountDeleted' => 'Konto gelöscht',
        'authGoogleCallbackDeleteAccount' => 'Konto löschen',
        'authGoogleCallbackLogin' => 'Anmelden',
        'bookmarks' => 'Lesezeichen',
        // Infinitive like every other action title in this file (Anmelden,
        // Passwort zurücksetzen) - the English imperative mood and its "Your"
        // don't survive into this slot.
        'checkInbox' => 'Postfach prüfen',
        'drafts' => 'Entwürfe & geplante Beiträge',
        'draftsEditDraft' => 'Entwurf bearbeiten',
        'draftsEditScheduled' => 'Geplanten Beitrag bearbeiten',
        'forgotPassword' => 'Passwort vergessen',
        'friendsFeed' => 'Freunde-Feed',
        'help' => 'Hilfe',
        'helpDescription' => 'Anleitungen und Antworten zur Nutzung der Seite.',
        'locations' => 'Orte',
        'locationsDescription' => 'Beiträge von den Orten, die dir am nächsten sind.',
        'locationsPlaceDescription' => 'Beiträge in der Nähe von {place}.',
        'login' => 'Anmelden',
        'loginVerificationCode' => 'Bestätigungscode',
        'map' => 'Karte',
        'mapDescription' => 'Eine Karte der Beiträge aus aller Welt - finde Menschen und Dinge in deiner Nähe.',
        'messages' => 'Nachrichten',
        'messagesWithUser' => 'Nachrichten mit {name}',
        'notifications' => 'Benachrichtigungen',
        'privacy' => 'Datenschutzerklärung',
        'quote' => 'Zitieren',
        'relayFeed' => 'Relay-Feed',
        'relayFeedDescription' => 'Öffentliche Beiträge von den Relays, die dieser Server abonniert hat.',
        'resetPassword' => 'Passwort zurücksetzen',
        'revertEmail' => 'E-Mail-Änderung rückgängig machen',
        'search' => 'Suche',
        'signup' => 'Registrieren',
        'tags' => 'Tags',
        'tagsDescription' => 'Entdecke angesagte und beliebte Hashtags auf {siteTitle}.',
        'tagsTagDescription' => 'Beiträge mit dem Tag {tag} auf {siteTitle}.',
        'terms' => 'Nutzungsbedingungen',
        // Not a literal translation of "Topics" - MainNavigation.topics
        // already calls this exact page 'Trends', so this matches it.
        'topics' => 'Trends',
        'topicsDescription' => 'Worüber auf {siteTitle} gerade gesprochen wird.',
        'topicsEntityDescription' => '{typeLabel} - Beiträge, die {entityTitle} auf {siteTitle} erwähnen.',
        'topicsTypeDescription' => '{typePlural}, über die auf {siteTitle} gerade gesprochen wird.',
        'unsubscribe' => 'E-Mail-Zusammenfassungen',
        'userFriends' => 'Freunde von {name}',
        'userFriendsDescription' => 'Freunde von {name} auf {siteTitle}',
        'userSettings' => 'Kontoeinstellungen',
        'users' => 'Nutzer',
        'verifyEmail' => 'E-Mail bestätigen',
    ],
];
