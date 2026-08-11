<?php

declare(strict_types=1);

/**
 * The moderator's and administrator's controls, in English - the buttons on the
 * report queue, the banned lists, the relay and server admin, and the badge on
 * the test page.
 *
 * Read by an administrator rather than by a visitor, but read in whatever
 * language they set the site to like everything else.
 */

return [
    'UserModButton' => [
        'make' => 'Make Mod',
        'remove' => 'Remove Mod',
    ],

    'UserUnbanButton' => [
        'name' => 'Unban',
    ],

    'TrendingEntityBanButton' => [
        'name' => 'Ban',
    ],

    'TrendingEntityUnbanButton' => [
        'name' => 'Unban',
    ],

    'ReportDismissButton' => [
        'name' => 'Dismiss',
    ],

    'ReportedContentClassifyButton' => [
        'name' => 'Mark Sensitive',
    ],

    'ServerUnblockButton' => [
        'name' => 'Unblock',
    ],

    'RelayUnsubscribeButton' => [
        'name' => 'Unsubscribe',
    ],

    'RememberedDeviceRevokeButton' => [
        'name' => 'Revoke',
    ],

    'TestResultsBadge' => [
        // {suite} is the suite's own name - "PHP", "JavaScript" - which is not
        // words and is the same everywhere. The verdict beside it is words.
        'passing' => '{suite}: Passing',
        'failing' => '{suite}: Failing',
    ],
];
