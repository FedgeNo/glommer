<?php

declare(strict_types=1);

/**
 * German for the controls that belong to no one feature - the media
 * carousel, the composer's file row, the notification dropdown, the help
 * search, and the odd button on a card somewhere. See
 * src/locales/en/Chrome.php for the source and the shape each entry is built
 * to.
 */

return [
    'CarouselAutoplayButton' => [
        'name' => 'Autoplay',
    ],

    'CarouselNextButton' => [
        'name' => 'Weiter',
    ],

    'CarouselPrevButton' => [
        'name' => 'Zurück',
    ],

    'MediaFullscreenButton' => [
        'name' => 'Vollbild',
    ],

    'ComposerFilesRemoveButton' => [
        'name' => 'Dateien entfernen',
    ],

    'NotificationDropdown' => [
        'showAll' => 'Alle anzeigen',
    ],

    'HelpSearch' => [
        'placeholder' => 'Hilfe durchsuchen…',
    ],

    'WelcomeBannerDismissButton' => [
        'name' => 'Verstanden',
    ],

    'StagedPostDiscardButton' => [
        'name' => 'Verwerfen',
    ],

    'StagedPostEditButton' => [
        'name' => 'Bearbeiten',
    ],

    'StagedPostPublishButton' => [
        'name' => 'Jetzt veröffentlichen',
    ],

    'LogoutButton' => [
        'name' => 'Abmelden',
    ],

    'AvatarUploadForm' => [
        'submit' => 'Avatar hochladen',
    ],

    'BannedUserSearchBox' => [
        'placeholder' => 'Gesperrte Nutzer durchsuchen…',
    ],

    'BannedUserSection' => [
        'heading' => 'Gesperrte Nutzer',
    ],

    'SentFriendRequestSection' => [
        // Not 'Ausstehende Anfragen' - ReceivedFriendRequestSection
        // (PostChrome.php) already owns that heading for the requests
        // waiting on you. This one names what it is so the two headings
        // stay distinguishable at a glance.
        'heading' => 'Gesendete Anfragen (ausstehend)',
    ],

    'BannedTrendingEntity' => [
        // The trailing space matters - the RelativeTime element renders
        // directly after 'before' with nothing else between them.
        'bannedBy' => ['before' => 'Gesperrt von {name} ', 'after' => ''],
        'reason' => ' - {reason}',
    ],

    'BlockedServerCard' => [
        'blockedBy' => ['before' => 'Blockiert von {name} ', 'after' => ''],
        // Dative, not nominative: this fills the same {name} slot a bare
        // username fills after 'von', and unlike a name, a common-noun
        // phrase has to carry its own case to read correctly there.
        'deletedAccount' => 'einem gelöschten Konto',
        'reason' => ' - {reason}',
    ],
];
