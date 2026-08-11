<?php

declare(strict_types=1);

/**
 * German for the moderation queue and relay admin pages. See
 * src/locales/en/ModerationAdmin.php for the source and the shape each entry
 * is built to.
 */

return [
    'ReportCard' => [
        'targetTypes' => [
            'post' => 'Beitrag',
            'message' => 'Nachricht',
            'user' => 'Nutzer',
        ],
        // Article-free, like deleteLabel below: {type} spans Beitrag (m),
        // Nachricht (f) and Nutzer (m), and a phrasing with an article or an
        // inflected ending could only be right for some of the three.
        'summary' => ['before' => '{type} #{id} gemeldet von ', 'after' => ''],
        'reasonLine' => 'Grund: {reason}',
        'banReporterLabel' => 'Melder sperren',
        'banReportedUserLabel' => 'Gemeldeten Nutzer sperren',
        'deleteLabel' => '{type} löschen',
        'reportedImageAlt' => 'Gemeldetes Bild',
        'attachmentUnavailable' => 'Ein gemeldeter Anhang ist nicht mehr verfügbar.',
        'viewAttachment' => 'Gemeldeten Anhang ansehen',
        'missing' => [
            'noSnapshot' => 'Der gemeldete Inhalt ist nicht mehr verfügbar.',
            'unknownType' => 'Unbekannter Inhaltstyp.',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => 'Keine Meldungen.',
    ],

    'ModQueueLinks' => [
        'intro' => 'Die Warteschlangen sind lang genug, dass man sie seitenweise liest, deshalb haben sie eigene Seiten.',
        'reportsLabel' => 'Meldungen',
        'bannedUsersLabel' => 'Gesperrte Nutzer',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'Noch hat kein Moderator etwas unternommen.',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'Keine gesperrten Trends.',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'Keine blockierten Server.',
    ],

    'RelayCard' => [
        'accepted' => 'Abonniert ',
        'waiting' => 'Wartet auf Bestätigung durch das Relay - abonniert ',
    ],

    'RelayList' => [
        'emptyNotice' => 'Keine Relays abonniert. Hier taucht nur auf, was von Konten kommt, denen Mitglieder direkt folgen.',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'Über ein Relay ist noch nichts hereingekommen. Beiträge erscheinen hier, sobald die Server auf der anderen Seite sie veröffentlichen.',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'Ein Relay abonnieren',
        'explainerOne' => 'Ein Relay ist ein gemeinsamer Strom aus allem: Jeder öffentliche Beitrag von jedem anderen abonnierten Server kommt hier an, und die Beiträge dieses Servers gehen an alle von ihnen hinaus. So findet eine neue Instanz überhaupt jemanden, denn die Föderation liefert sonst nur das, wofür es hier bereits Follows gibt.',
        'explainerTwo' => 'Die Last lässt sich nicht vorhersagen - es ist, was auch immer diese Server veröffentlichen, eine Woche ruhig und in der nächsten Tausende Beiträge pro Stunde, und dein Speicher, deine Zustellungswarteschlange und deine Moderationswarteschlange tragen das alles mit. Über das Relay eingehende Beiträge bleiben außerhalb des Haupt-Feeds und des Freunde-Feeds; sie landen im Relay-Feed, den man bewusst öffnet.',
        'addressLabel' => 'Relay-Adresse',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => 'Abonnieren',
    ],

    'RelayFollowObjectField' => [
        'label' => 'Abonnementart',
        'options' => [
            'public' => 'Dem öffentlichen Stream folgen (was die meisten Relays erwarten)',
            'actor' => 'Dem eigenen Actor des Relays folgen',
        ],
        'retryNotice' => 'Falls das Relay nie akzeptiert, ziehe zurück und probiere den anderen Stil - manche Relay-Software erkennt nur eine der beiden Arten.',
    ],

    'SiteCounters' => [
        'members' => 'Mitglieder: {count} ({joined} in den letzten {days} Tagen beigetreten)',
        'activeMembers' => 'Mitglieder hier in den letzten {days} Tagen: {count} ({posted} davon haben gepostet)',
        'posts' => 'Hier geschriebene Beiträge: {count} ({recent} in den letzten {days} Tagen)',
        'deliveries' => 'Föderierte Zustellungen in den letzten {days} Tagen: {delivered} angekommen, {undeliverable} aufgegeben',
        'queued' => 'Wartend auf Versand: {count} ({failing} bereits mindestens einmal abgelehnt)',
        'pendingReads' => 'Beiträge, die noch von anderen Servern gelesen werden müssen: {count}',
    ],

    'TestSuitePanel' => [
        'intro' => 'Führt die Testsuite der Seite aus und zeigt die Ergebnisse. Das dauert einige Sekunden, deshalb öffnet es sich auf einer eigenen Seite.',
        'runLabel' => 'Tests ausführen',
    ],

    'HelpArticle' => [
        'backLabel' => 'Zurück zur Hilfeübersicht',
    ],

    'UserSearchList' => [
        'noSuggestions' => 'Gerade keine Vorschläge - Vorschläge kommen von den Freunden der Leute, mit denen du schon befreundet bist. Suche oben, um jemanden über den Namen zu finden.',
        'noMatches' => 'Darauf passt hier niemand.',
    ],
];
