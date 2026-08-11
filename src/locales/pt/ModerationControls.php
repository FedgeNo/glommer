<?php

declare(strict_types=1);

/**
 * The moderator's and administrator's controls, in Portuguese - the buttons
 * on the report queue, the banned lists, the relay and server admin, and the
 * badge on the test page. See src/locales/en/ModerationControls.php for what
 * these classes are.
 */

return [
    'UserModButton' => [
        'make' => 'Tornar moderador',
        'remove' => 'Remover moderador',
    ],

    'UserUnbanButton' => [
        'name' => 'Levantar banimento',
    ],

    'TrendingEntityBanButton' => [
        'name' => 'Banir',
    ],

    'TrendingEntityUnbanButton' => [
        // Same phrase as UserUnbanButton - English uses the same word for
        // both, and "des-" has no clean match on "banir" the way it does on
        // "bloquear".
        'name' => 'Levantar banimento',
    ],

    'ReportDismissButton' => [
        'name' => 'Descartar',
    ],

    'ReportedContentClassifyButton' => [
        'name' => 'Marcar como sensível',
    ],

    'ServerUnblockButton' => [
        'name' => 'Desbloquear',
    ],

    'RelayUnsubscribeButton' => [
        'name' => 'Cancelar subscrição',
    ],

    'RememberedDeviceRevokeButton' => [
        'name' => 'Revogar',
    ],

    'TestResultsBadge' => [
        'passing' => '{suite}: A passar',
        'failing' => '{suite}: A falhar',
    ],
];
