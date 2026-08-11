<?php

declare(strict_types=1);

/**
 * German for the controls on somebody else's profile - befriending them,
 * following them across the Fediverse, writing to them, and the two ways of
 * having nothing more to do with them. See src/locales/en/Friendship.php for
 * the source and the shape each entry is built to.
 */

return [
    'FriendRemoveButton' => [
        'name' => 'Freund entfernen',
    ],

    'FriendRequestAcceptButton' => [
        'name' => 'Annehmen',
    ],

    'FriendRequestDenyButton' => [
        'name' => 'Ablehnen',
    ],

    'UserFollowButton' => [
        'follow' => 'Folgen',
        'unfollow' => 'Entfolgen',
    ],

    'UserBlockButton' => [
        'name' => 'Blockieren',
    ],

    'UserUnblockButton' => [
        // Not 'Entsperren' - this app keeps that verb for lifting a
        // moderation ban (see ModerationControls.php) separate from lifting
        // a block, so the two undo actions read as the different things
        // they are.
        'name' => 'Blockierung aufheben',
    ],

    'OtherUser' => [
        'message' => 'Nachricht',
    ],
];
