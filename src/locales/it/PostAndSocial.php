<?php

declare(strict_types=1);

/**
 * Italian for the composer, poll and feed-context classes. See
 * src/locales/en/PostAndSocial.php for what this fragment covers.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => 'Vedi ', 'link' => 'altre posizioni', 'after' => ''],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'Post vicino a te',
        'description' => 'Qui trovi i post più vicini a un punto, ovunque ci sia attività, per quanto lontana. Condividi la tua posizione per iniziare da dove ti trovi, oppure scegli un punto sulla mappa.',
        'useMyLocation' => 'Usa la mia posizione',
        'pickOnMap' => 'Scegli sulla mappa',
        'searchPlaceholder' => 'Oppure scrivi il nome di un luogo…',
        'searchLabel' => 'Cerca un luogo',
        'locating' => 'Localizzazione in corso…',
        'noGeolocation' => 'Il tuo browser non può condividere una posizione.',
        'locationError' => 'Impossibile ottenere la tua posizione. Controlla i permessi di localizzazione del browser.',
    ],

    'PollDeadline' => [
        'final' => 'Risultato finale',
        'closes' => ['before' => 'Si chiude ', 'after' => ''],
        // "tra" lives inside each unit's own phrasing rather than as a shared
        // prefix, mirroring how English keeps "in" inside each - a language
        // may combine the count and the word for "in" differently per unit.
        'days' => ['one' => 'tra {count} giorno', 'other' => 'tra {count} giorni'],
        'hours' => ['one' => 'tra {count} ora', 'other' => 'tra {count} ore'],
        'minutes' => ['one' => 'tra {count} minuto', 'other' => 'tra {count} minuti'],
        'underMinute' => 'tra meno di un minuto',
    ],

    'PollTally' => [
        // Trailing space: this sits directly before PollDeadline's own words
        // with nothing else between them.
        'voters' => ['one' => '1 persona ha votato ', 'other' => '{count} persone hanno votato '],
    ],

    'PostComposer' => [
        'prompt' => ['before' => '', 'link' => 'Accedi', 'after' => ' per pubblicare.'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => '', 'link' => 'Accedi', 'after' => ' per rispondere.'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => '', 'after' => ' ha ripubblicato'],
    ],

    'ThreadContext' => [
        'response' => ['before' => 'In risposta a ', 'after' => ''],
        'untitled' => 'questo post',
        'jumpToStart' => 'Vai all\'inizio',
    ],

    'TopicSummaryCard' => [
        'label' => 'Riepilogo generato dall\'IA',
    ],

    'WelcomeBanner' => [
        // "Ti diamo il benvenuto" rather than "Benvenuto/a": "il benvenuto" is
        // a fixed masculine noun here (the welcome we give you), not an
        // adjective agreeing with the reader's own unknown gender.
        'heading' => ['before' => 'Ti diamo il benvenuto su ', 'after' => ''],
        'paragraphs' => [
            'Scrivi qualcosa nel riquadro qui sotto e verrà pubblicato sul tuo feed. Chiunque può rispondere, e una risposta non è altro che un post collegato a un altro, quindi le conversazioni si annidano quanto serve.',
            'Aggiungi persone come amici e i loro post entreranno a far parte del tuo feed. Il feed Globale - il nome del sito, in alto a sinistra - mostra tutto ciò che viene scritto qui, il posto giusto per trovare qualcuno da aggiungere.',
            'Questo sito fa parte del Fediverso: puoi seguire account su Mastodon e altri server tramite il loro identificativo completo, e ciò che pubblichi raggiunge le persone che ti seguono lì. Cerca un identificativo come @someone@example.social e questo server andrà a trovarlo.',
            'Aggiungi #hashtag a un post e comparirà nella pagina di quel tag, e in Tendenze se abbastanza persone ne stanno scrivendo.',
            'I messaggi tra i membri sono cifrati end-to-end: il server memorizza testo cifrato che non può leggere. Attivali nelle Impostazioni.',
            'Non devi pubblicare subito: salva una bozza, oppure imposta un orario e si pubblicherà da solo. Entrambi si trovano in Bozze e programmati.',
        ],
        'more' => ['before' => 'Trovi altro nelle ', 'link' => 'pagine di aiuto', 'after' => ', incluso come trasferire qui un account da un altro posto.'],
        'dontShowAgain' => 'Non mostrarlo più',
    ],
];
