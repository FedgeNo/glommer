<?php

declare(strict_types=1);

/**
 * The words on a member and the controls beside them, in English - the profile
 * line, the friend-request button, and what a post that has not gone out yet
 * says it is waiting for.
 */

return [
    'User' => [
        // {date} is the joining date, written by DateFormat in this locale's own
        // month names and order - so a phrasing may govern it the way its
        // language governs a date.
        'joined' => 'Joined {date}',
    ],

    'FriendRequestButton' => [
        'add' => 'Add Friend',
        'cancel' => 'Cancel Request',
    ],

    'StagedPostWhen' => [
        'scheduled' => 'Scheduled for {when}',
        'draft' => 'Draft - publishes only when you say so',
    ],
];
