<?php

declare(strict_types=1);

/**
 * Italian for the moderation queue and relay admin pages. See
 * src/locales/en/ModerationAdmin.php for what this fragment covers.
 */

return [
    'ReportCard' => [
        'targetTypes' => [
            'post' => 'Post',
            'message' => 'Messaggio',
            'user' => 'Utente',
        ],
        // Rebuilt around "Segnalazione su" (a report about) rather than a
        // past participle agreeing with {type} ("segnalato"/"segnalata") -
        // the token can be any of several genders and the phrasing has to
        // hold for all of them, the same problem Spanish's own summary
        // solves the same way.
        'summary' => ['before' => 'Segnalazione su {type} #{id}, da ', 'after' => ''],
        'reasonLine' => 'Motivo: {reason}',
        'banReporterLabel' => 'Banna il segnalante',
        'banReportedUserLabel' => 'Banna l\'utente segnalato',
        'deleteLabel' => 'Elimina {type}',
        'reportedImageAlt' => 'Immagine segnalata',
        'attachmentUnavailable' => 'Un allegato segnalato non è più disponibile.',
        'viewAttachment' => 'Visualizza l\'allegato segnalato',
        'missing' => [
            'noSnapshot' => 'Il contenuto segnalato non è più disponibile.',
            'unknownType' => 'Tipo di contenuto sconosciuto.',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => 'Nessuna segnalazione.',
    ],

    'ModQueueLinks' => [
        'intro' => 'Le code sono abbastanza lunghe da leggere una pagina alla volta, quindi hanno pagine proprie.',
        'reportsLabel' => 'Segnalazioni',
        'bannedUsersLabel' => 'Utenti bannati',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'Nessun moderatore ha ancora fatto nulla.',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'Nessuna entità di tendenza bannata.',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'Nessun server bloccato.',
    ],

    'RelayCard' => [
        'accepted' => 'Iscritto ',
        'waiting' => 'In attesa che il relay accetti - iscritto ',
    ],

    'RelayList' => [
        // Nominalized ("Nessuna iscrizione") rather than "Non sei iscritto/a":
        // the reader's gender is unknown, and the noun does not agree with it
        // the way the participle would.
        'emptyNotice' => 'Nessuna iscrizione a un relay. Qui non arriva nulla se non ciò che i membri seguono.',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'Non è ancora arrivato nulla tramite un relay. I post compaiono qui man mano che i server dall\'altra parte li pubblicano.',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'Iscriviti a un relay',
        'explainerOne' => 'Un relay è un flusso condiviso e non filtrato: qui arriva ogni post pubblico da ogni altro server iscritto, e quelli di questo server vanno a tutti loro. È così che una nuova istanza trova qualcuno, dato che altrimenti la federazione porta solo ciò che qualcuno qui già segue.',
        'explainerTwo' => 'Il carico non è prevedibile: è quello che quei server pubblicano, tranquillo per una settimana e con migliaia di post all\'ora quella dopo, e il tuo spazio di archiviazione, la coda di consegna e la coda di moderazione lo ricevono per intero. I post arrivati tramite relay restano fuori dal feed principale e da quello degli amici; finiscono nel Feed relay, che le persone aprono di proposito.',
        'addressLabel' => 'Indirizzo del relay',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => 'Iscriviti',
    ],

    'RelayFollowObjectField' => [
        'label' => 'Stile di iscrizione',
        'options' => [
            'public' => 'Segui il flusso pubblico (quello che la maggior parte dei relay si aspetta)',
            'actor' => 'Segui l\'attore del relay',
        ],
        'retryNotice' => 'Se il relay non accetta mai, ritira l\'iscrizione e prova l\'altro stile - alcuni software per relay ne riconoscono solo uno.',
    ],

    'SiteCounters' => [
        'members' => 'Membri: {count} ({joined} iscritti negli ultimi {days} giorni)',
        'activeMembers' => 'Membri qui negli ultimi {days} giorni: {count} ({posted} di loro hanno pubblicato)',
        'posts' => 'Post scritti qui: {count} ({recent} negli ultimi {days} giorni)',
        'deliveries' => 'Consegne federate negli ultimi {days} giorni: {delivered} arrivate, {undeliverable} abbandonate',
        'queued' => 'In attesa di uscire: {count} ({failing} già rifiutate almeno una volta)',
        'pendingReads' => 'Post in attesa di essere letti da altri server: {count}',
    ],

    'TestSuitePanel' => [
        'intro' => 'Esegui la suite di test del sito e visualizza i risultati. Richiede alcuni secondi, quindi si apre in una pagina propria.',
        'runLabel' => 'Esegui i test',
    ],

    'HelpArticle' => [
        'backLabel' => 'Torna a tutta la guida',
    ],

    'UserSearchList' => [
        // "in amicizia con" (a fixed, invariable phrase) rather than "sei già
        // amico/a di": the reader's gender is unknown, and this way nothing
        // has to agree with it.
        'noSuggestions' => 'Nessun suggerimento al momento - i suggerimenti provengono dagli amici delle persone con cui sei già in amicizia. Cerca qui sopra per trovare qualcuno per nome.',
        'noMatches' => 'Nessuno qui corrisponde a questo.',
    ],
];
