<?php

declare(strict_types=1);

/**
 * German for the composer, poll and feed-context classes. See
 * src/locales/en/PostAndSocial.php for the source and the shape each entry is
 * built to.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => 'Siehe ', 'link' => 'weitere Orte', 'after' => ''],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'Beiträge in deiner Nähe',
        'description' => 'Das zeigt die Beiträge, die einem Punkt am nächsten sind - wo auch immer etwas los ist, egal wie weit weg. Teile deinen Standort, um von dort zu starten, wo du bist, oder wähle stattdessen einen Punkt auf der Karte.',
        'useMyLocation' => 'Meinen Standort verwenden',
        'pickOnMap' => 'Auf der Karte wählen',
        'searchPlaceholder' => 'Oder gib einen Ortsnamen ein…',
        'searchLabel' => 'Nach einem Ort suchen',
        'locating' => 'Standort wird ermittelt…',
        'noGeolocation' => 'Dein Browser kann keinen Standort teilen.',
        'locationError' => 'Standort konnte nicht ermittelt werden. Prüfe die Standortberechtigung deines Browsers.',
    ],

    'PollDeadline' => [
        'final' => 'Endergebnis',
        'closes' => ['before' => 'Endet ', 'after' => ''],
        'days' => ['one' => 'in {count} Tag', 'other' => 'in {count} Tagen'],
        'hours' => ['one' => 'in {count} Stunde', 'other' => 'in {count} Stunden'],
        'minutes' => ['one' => 'in {count} Minute', 'other' => 'in {count} Minuten'],
        'underMinute' => 'in weniger als einer Minute',
    ],

    'PollTally' => [
        'voters' => ['one' => '1 Person hat abgestimmt ', 'other' => '{count} Personen haben abgestimmt '],
    ],

    'PostComposer' => [
        'prompt' => ['before' => 'Zum Posten bitte ', 'link' => 'anmelden', 'after' => '.'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => 'Zum Antworten bitte ', 'link' => 'anmelden', 'after' => '.'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => '', 'after' => ' hat das geteilt'],
    ],

    'ThreadContext' => [
        'response' => ['before' => 'Als Antwort auf ', 'after' => ''],
        'untitled' => 'diesen Beitrag',
        'jumpToStart' => 'Zum Anfang springen',
    ],

    'TopicSummaryCard' => [
        'label' => 'KI-generierte Zusammenfassung',
    ],

    'WelcomeBanner' => [
        'heading' => ['before' => 'Willkommen bei ', 'after' => ''],
        'paragraphs' => [
            'Schreib etwas in das Feld unten, und es geht an deinen Feed. Jeder kann antworten, und eine Antwort ist einfach ein Beitrag mit einem übergeordneten Beitrag, sodass sich Unterhaltungen so tief verschachteln, wie sie müssen.',
            'Füge Leute als Freunde hinzu, und ihre Beiträge erscheinen in deinem Feed. Der Globale Feed - der Name der Website oben links - zeigt alles, was hier geschrieben wurde, und ist der richtige Ort, um jemanden zum Hinzufügen zu finden.',
            'Diese Seite ist Teil des Fediverse: Du kannst Konten auf Mastodon und anderen Servern über ihr vollständiges Handle folgen, und was du postest, erreicht die Leute, die dir dort folgen. Suche nach einem Handle wie @someone@example.social, und dieser Server sucht die Person für dich.',
            'Versieh einen Beitrag mit #Hashtags, und er erscheint auf der Seite dieses Tags und in den Trends, wenn genug Leute darüber schreiben.',
            'Nachrichten zwischen Mitgliedern sind Ende-zu-Ende-verschlüsselt - der Server speichert nur Chiffretext, den er nicht lesen kann. Aktiviere das in den Einstellungen.',
            'Du musst nicht sofort posten: Speichere einen Entwurf, oder lege eine Uhrzeit fest, zu der er sich von selbst veröffentlicht. Beides findest du unter Entwürfe & geplante Beiträge.',
        ],
        'more' => ['before' => 'Mehr dazu findest du auf den ', 'link' => 'Hilfeseiten', 'after' => ', unter anderem, wie du ein Konto von anderswo hierher umziehst.'],
        'dontShowAgain' => 'Das nicht mehr anzeigen',
    ],
];
