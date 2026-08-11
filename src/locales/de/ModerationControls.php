<?php

declare(strict_types=1);

/**
 * German for the moderator's and administrator's controls - the buttons on
 * the report queue, the banned lists, the relay and server admin, and the
 * badge on the test page. See src/locales/en/ModerationControls.php for the
 * source and the shape each entry is built to.
 */

return [
    'UserModButton' => [
        // Spelt out rather than 'Mod' - see MainNavigation.php's modSettings
        // comment: unqualified 'Mod' reads as Modifikation (game mods) at
        // least as readily as Moderator.
        'make' => 'Moderator machen',
        'remove' => 'Moderator entfernen',
    ],

    'UserUnbanButton' => [
        'name' => 'Entsperren',
    ],

    'TrendingEntityBanButton' => [
        'name' => 'Sperren',
    ],

    'TrendingEntityUnbanButton' => [
        'name' => 'Entsperren',
    ],

    'ReportDismissButton' => [
        'name' => 'Verwerfen',
    ],

    'ReportedContentClassifyButton' => [
        'name' => 'Sensibel markieren',
    ],

    'ServerUnblockButton' => [
        // Not 'Entsperren' - see Friendship.php's UserUnblockButton comment;
        // 'sperren' is reserved for a ban, 'blockieren' for a block, and this
        // undoes the latter.
        'name' => 'Blockierung aufheben',
    ],

    'RelayUnsubscribeButton' => [
        'name' => 'Abbestellen',
    ],

    'RememberedDeviceRevokeButton' => [
        'name' => 'Widerrufen',
    ],

    'TestResultsBadge' => [
        'passing' => '{suite}: Bestanden',
        'failing' => '{suite}: Fehlgeschlagen',
    ],
];
