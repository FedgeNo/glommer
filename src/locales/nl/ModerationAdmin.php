<?php

declare(strict_types=1);

/**
 * Dutch for the moderation queue and relay admin pages. See
 * src/locales/en/ModerationAdmin.php for the source and the shape each entry
 * is built to.
 */

return [
    'ReportCard' => [
        'targetTypes' => [
            'post' => 'Post',
            'message' => 'Bericht',
            'user' => 'Gebruiker',
        ],
        'summary' => ['before' => '{type} #{id} gerapporteerd door ', 'after' => ''],
        'reasonLine' => 'Reden: {reason}',
        'banReporterLabel' => 'Melder verbannen',
        'banReportedUserLabel' => 'Gerapporteerde gebruiker verbannen',
        'deleteLabel' => '{type} verwijderen',
        'reportedImageAlt' => 'Gerapporteerde afbeelding',
        'attachmentUnavailable' => 'Een gerapporteerde bijlage is niet meer beschikbaar.',
        'viewAttachment' => 'Gerapporteerde bijlage bekijken',
        'missing' => [
            'noSnapshot' => 'De gerapporteerde inhoud is niet meer beschikbaar.',
            'unknownType' => 'Onbekend inhoudstype.',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => 'Geen rapporten.',
    ],

    'ModQueueLinks' => [
        'intro' => 'De wachtrijen zijn lang genoeg om per pagina te lezen, dus ze hebben hun eigen pagina\'s.',
        'reportsLabel' => 'Rapporten',
        'bannedUsersLabel' => 'Verbannen gebruikers',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'Nog geen moderator heeft iets gedaan.',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'Geen verbannen trending-entiteiten.',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'Geen geblokkeerde servers.',
    ],

    'RelayCard' => [
        'accepted' => 'Geabonneerd ',
        'waiting' => 'Wachten tot de relay accepteert - geabonneerd ',
    ],

    'RelayList' => [
        'emptyNotice' => 'Niet geabonneerd op relays. Hier komt niets binnen behalve wat leden volgen.',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'Er is nog niets binnengekomen via een relay. Posts verschijnen hier zodra de servers aan de andere kant ze publiceren.',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'Abonneren op een relay',
        'explainerOne' => 'Een relay is een gedeelde firehose: elke openbare post van elke andere geabonneerde server komt hier binnen, en die van deze server gaan naar al die servers. Zo vindt een nieuwe instantie überhaupt iemand, want federatie draagt anders alleen over wat iemand hier al volgt.',
        'explainerTwo' => 'De belasting is niet aan jou te voorspellen - het is wat die servers publiceren, de ene week stil en de volgende duizenden posts per uur, en je opslag, bezorgwachtrij en moderatiewachtrij dragen dit allemaal. Gerelayde posts blijven buiten de hoofd- en vriendenfeed; ze gaan naar de Relayfeed, die mensen bewust openen.',
        'addressLabel' => 'Relayadres',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => 'Abonneren',
    ],

    'RelayFollowObjectField' => [
        'label' => 'Abonneerstijl',
        'options' => [
            'public' => 'De openbare stream volgen (wat de meeste relays verwachten)',
            'actor' => 'De eigen actor van de relay volgen',
        ],
        'retryNotice' => 'Als de relay nooit accepteert, trek je aanvraag dan in en probeer de andere stijl - sommige relaysoftware herkent er maar één van.',
    ],

    'SiteCounters' => [
        'members' => 'Leden: {count} ({joined} in de afgelopen {days} dagen lid geworden)',
        'activeMembers' => 'Leden hier in de afgelopen {days} dagen: {count} (waarvan {posted} hebben gepost)',
        'posts' => 'Hier geschreven posts: {count} ({recent} in de afgelopen {days} dagen)',
        'deliveries' => 'Gefedereerde bezorgingen in de afgelopen {days} dagen: {delivered} aangekomen, {undeliverable} opgegeven',
        'queued' => 'Wachtend om te worden verzonden: {count} ({failing} al minstens één keer geweigerd)',
        'pendingReads' => 'Posts die wachten om te worden gelezen van andere servers: {count}',
    ],

    'TestSuitePanel' => [
        'intro' => 'Voer de testsuite van de site uit en bekijk de resultaten. Dit duurt enkele seconden, dus het opent op een eigen pagina.',
        'runLabel' => 'Tests uitvoeren',
    ],

    'HelpArticle' => [
        'backLabel' => 'Terug naar alle hulp',
    ],

    'UserSearchList' => [
        'noSuggestions' => 'Op dit moment geen suggesties - suggesties komen van de vrienden van mensen met wie je al bevriend bent. Zoek hierboven om iemand op naam te vinden.',
        'noMatches' => 'Niemand hier komt daarmee overeen.',
    ],
];
