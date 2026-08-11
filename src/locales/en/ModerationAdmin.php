<?php

declare(strict_types=1);

/**
 * The words the moderation queue and relay admin pages say, in English. See
 * src/locales/en.php for what this file is and why it exists apart from it.
 */

return [
    'ReportCard' => [
        // The report's own kind, shown ahead of its id - not chosen by the
        // admin, so it is a lookup keyed on the same 'post'/'message'/'user'
        // the database already stores rather than a second name to invent.
        'targetTypes' => [
            'post' => 'Post',
            'message' => 'Message',
            'user' => 'User',
        ],
        // {type} and {id} are filled in rather than glued on either side of
        // this piece, since a language may not put the kind before the
        // number. The reporter's name is the link the sentence wraps; it is
        // data, not a phrasing, so it carries no piece of its own.
        'summary' => ['before' => '{type} #{id} reported by ', 'after' => ''],
        'reasonLine' => 'Reason: {reason}',
        'banReporterLabel' => 'Ban Reporter',
        'banReportedUserLabel' => 'Ban Reported User',
        'deleteLabel' => 'Delete {type}',
        'reportedImageAlt' => 'Reported image',
        'attachmentUnavailable' => 'A reported attachment is no longer available.',
        'viewAttachment' => 'View Reported Attachment',
        // Two different reasons the reported item itself can't be shown -
        // kept apart because they are different questions ("did we keep a
        // copy" vs "was this ever a type we understand"), not the same notice
        // twice.
        'missing' => [
            'noSnapshot' => 'The reported content is no longer available.',
            'unknownType' => 'Unknown content type.',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => 'No reports.',
    ],

    'ModQueueLinks' => [
        'intro' => 'The queues are long enough to read a page at a time, so they have pages of their own.',
        'reportsLabel' => 'Reports',
        'bannedUsersLabel' => 'Banned Users',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'No moderator has done anything yet.',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'No banned trending entities.',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'No blocked servers.',
    ],

    'RelayCard' => [
        'accepted' => 'Subscribed ',
        'waiting' => 'Waiting for the relay to accept - subscribed ',
    ],

    'RelayList' => [
        'emptyNotice' => 'Not subscribed to any relays. Nothing arrives here except what members follow.',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'Nothing has come through a relay yet. Posts appear here as the servers on the other side publish them.',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'Subscribe to a Relay',
        'explainerOne' => 'A relay is a shared firehose: every public post from every other subscribed server arrives here, and this server\'s go out to all of them. It is how a new instance finds anyone at all, since federation otherwise only carries what somebody here already follows.',
        'explainerTwo' => 'The load is not yours to predict - it is whatever those servers publish, quiet one week and thousands of posts an hour the next, and your storage, delivery queue and moderation queue all carry it. Relayed posts stay out of the main and friends feeds; they go to the Relay Feed, which people open deliberately.',
        'addressLabel' => 'Relay Address',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => 'Subscribe',
    ],

    'RelayFollowObjectField' => [
        'label' => 'Subscription Style',
        'options' => [
            'public' => 'Follow the public stream (what most relays expect)',
            'actor' => 'Follow the relay\'s own actor',
        ],
        'retryNotice' => 'If the relay never accepts, withdraw and try the other style - some relay software only recognises one of them.',
    ],

    'SiteCounters' => [
        'members' => 'Members: {count} ({joined} joined in the last {days} days)',
        'activeMembers' => 'Members here in the last {days} days: {count} ({posted} of them posted)',
        'posts' => 'Posts written here: {count} ({recent} in the last {days} days)',
        'deliveries' => 'Federated deliveries in the last {days} days: {delivered} arrived, {undeliverable} given up on',
        'queued' => 'Waiting to go out: {count} ({failing} already refused at least once)',
        'pendingReads' => 'Posts waiting to be read from other servers: {count}',
    ],

    'TestSuitePanel' => [
        'intro' => 'Run the site\'s test suite and see the results. It takes a few seconds, so it opens on its own page.',
        'runLabel' => 'Run Tests',
    ],

    'HelpArticle' => [
        'backLabel' => 'Back to All Help',
    ],

    'UserSearchList' => [
        'noSuggestions' => 'No suggestions right now - suggestions come from the friends of people you are already friends with. Search above to find someone by name.',
        'noMatches' => 'Nobody here matches that.',
    ],
];
