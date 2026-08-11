<?php

declare(strict_types=1);

/**
 * The controls that belong to no one feature, in English - the media carousel,
 * the composer's file row, the notification dropdown, the help search, and the
 * odd button on a card somewhere.
 */

return [
    'CarouselAutoplayButton' => [
        'name' => 'Autoplay',
    ],

    'CarouselNextButton' => [
        'name' => 'Next',
    ],

    'CarouselPrevButton' => [
        'name' => 'Previous',
    ],

    'MediaFullscreenButton' => [
        'name' => 'Fullscreen',
    ],

    'ComposerFilesRemoveButton' => [
        'name' => 'Remove Files',
    ],

    'NotificationDropdown' => [
        'showAll' => 'Show All',
    ],

    'HelpSearch' => [
        'placeholder' => 'Search help…',
    ],

    'WelcomeBannerDismissButton' => [
        'name' => 'Got It',
    ],

    'StagedPostDiscardButton' => [
        'name' => 'Discard',
    ],

    'StagedPostEditButton' => [
        'name' => 'Edit',
    ],

    'StagedPostPublishButton' => [
        'name' => 'Publish Now',
    ],

    'LogoutButton' => [
        'name' => 'Log Out',
    ],

    'AvatarUploadForm' => [
        'submit' => 'Update Avatar',
    ],

    'BannedUserSearchBox' => [
        'placeholder' => 'Search banned users…',
    ],

    'BannedUserSection' => [
        'heading' => 'Banned Users',
    ],

    'SentFriendRequestSection' => [
        'heading' => 'Sent Requests (Awaiting Response)',
    ],

    'BannedTrendingEntity' => [
        // {name} is the moderator who did it, and the trailing space is where
        // the time element goes - so a language may end on the name instead.
        'bannedBy' => ['before' => 'Banned by {name} ', 'after' => ''],
        'reason' => ' - {reason}',
    ],

    'BlockedServerCard' => [
        'blockedBy' => ['before' => 'Blocked by {name} ', 'after' => ''],
        // Who did it, when the account that did is gone. Substituted into
        // {name} above rather than being a sentence of its own.
        'deletedAccount' => 'a deleted account',
        'reason' => ' - {reason}',
    ],
];
