<?php

declare(strict_types=1);

/**
 * Dutch for the moderator's and administrator's controls - the buttons on
 * the report queue, the banned lists, the relay and server admin, and the
 * badge on the test page. See src/locales/en/ModerationControls.php for the
 * source and the shape each entry is built to.
 */

return [
    'UserModButton' => [
        'make' => 'Mod maken',
        'remove' => 'Mod verwijderen',
    ],

    'UserUnbanButton' => [
        'name' => 'Verbanning opheffen',
    ],

    'TrendingEntityBanButton' => [
        'name' => 'Verbannen',
    ],

    'TrendingEntityUnbanButton' => [
        'name' => 'Verbanning opheffen',
    ],

    'ReportDismissButton' => [
        'name' => 'Afwijzen',
    ],

    'ReportedContentClassifyButton' => [
        'name' => 'Markeren als gevoelig',
    ],

    'ServerUnblockButton' => [
        'name' => 'Deblokkeren',
    ],

    'RelayUnsubscribeButton' => [
        'name' => 'Opzeggen',
    ],

    'RememberedDeviceRevokeButton' => [
        'name' => 'Intrekken',
    ],

    'TestResultsBadge' => [
        'passing' => '{suite}: geslaagd',
        'failing' => '{suite}: mislukt',
    ],
];
