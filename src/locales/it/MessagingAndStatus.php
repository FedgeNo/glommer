<?php

declare(strict_types=1);

/**
 * Italian for the messaging and admin-status classes. See
 * src/locales/en/MessagingAndStatus.php for what this fragment covers.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => 'Hai dimenticato la passphrase? Reimpostandola si creano nuove chiavi sotto una nuova passphrase - ma i messaggi cifrati con le vecchie chiavi non potranno mai più essere letti, da nessuno.',
        'requirements' => 'Almeno 12 caratteri, e non la password del tuo account - quella viene inviata a questo server, mentre la tua passphrase non deve esserlo mai.',
        'passphraseLabel' => 'Passphrase',
        'resetPassphraseLabel' => 'Nuova passphrase',
        'confirmLabel' => 'Conferma passphrase',
        'accountPasswordLabel' => 'Password dell\'account',
        'submitLabel' => 'Attiva i messaggi cifrati',
        'resetSubmitLabel' => 'Reimposta le chiavi di cifratura',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'I messaggi cifrati end-to-end vengono bloccati e sbloccati nel tuo browser: questo server li inoltra e li memorizza senza poterli leggere. La tua chiave è protetta da una passphrase, e la stessa passphrase sblocca i tuoi messaggi da qualsiasi browser. Le conversazioni vengono cifrate non appena entrambe le persone hanno attivato questa funzione; i messaggi verso persone su altri server restano non cifrati, perché la federazione non ha altro modo di trasportarli.',
        'noRecovery' => 'Non c\'è modo di recuperare una passphrase perduta - nemmeno per l\'amministratore. Perderla significa perdere i tuoi messaggi cifrati.',
        'enabledStatus' => 'I messaggi cifrati sono attivi.',
    ],

    'MessagePrivacyButton' => [
        'encrypted' => [
            'label' => '🔒 Cifrato',
            // "avere la certezza" rather than "essere certo/a": the reader's
            // gender is unknown, and the noun construction does not agree
            // with it the way the adjective would.
            'explanation' => 'I messaggi in questa conversazione sono cifrati end-to-end: vengono sbloccati con la tua passphrase e letti nei vostri browser, e ciò che questo server memorizza è testo cifrato. Controlla il codice di sicurezza in fondo alla conversazione con l\'altra persona per avere la certezza che non ci sia nessuno in mezzo. I messaggi inviati prima che la cifratura fosse attivata restano leggibili come erano.',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 Non cifrato',
            'explanation' => 'I messaggi qui saranno cifrati end-to-end non appena {handle} attiverà i messaggi cifrati nelle proprie impostazioni.',
        ],
        'awaiting-yours' => [
            'label' => '🔓 Non cifrato',
            'explanation' => 'I messaggi qui non sono cifrati end-to-end. Attiva i messaggi cifrati nelle Impostazioni per proteggere questa conversazione.',
        ],
        'federated' => [
            'label' => '🔓 Non cifrato',
            'explanation' => '{handle} si trova su un altro server. I messaggi in questa conversazione sono memorizzati sia su quel server sia su questo, e il suo amministratore può leggerli - il protocollo tra i server non ha modo di cifrarli. Riserva ciò che è delicato alle conversazioni su questo sito.',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'Segui account del Fediverso',
        'notice' => 'Incolla uno o più identificativi, ad es. @user@example.social - qualsiasi separatore tra loro funziona.',
        'handlesLabel' => 'Identificativi del Fediverso da seguire',
        'submit' => 'Segui',
        'statusPending' => 'in attesa',
        'statusAccepted' => 'accettato',
    ],

    'ServerBlockForm' => [
        'legend' => 'Blocca un server',
        'description' => 'Rifiuta tutto ciò che arriva da quel server e da tutto ciò che ne dipende: nessuna consegna in entrata, nessuna in uscita, e i follow già esistenti in entrambe le direzioni vengono interrotti.',
        'serverLabel' => 'Server',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => 'Motivo',
        'reasonPlaceholder' => 'Perché questo server è bloccato',
        'submit' => 'Blocca server',
    ],

    'VideoCallTestPanel' => [
        'intro' => 'Esegue le parti della configurazione della chiamata che si possono controllare da un solo browser. Tutto ciò che precede una vera connessione peer-to-peer è testabile qui; per connettersi a un\'altra persona serve quella persona.',
    ],

    'VideoCallTestButton' => [
        'label' => 'Esegui il controllo',
    ],

    'WebSocketStatus' => [
        'ok' => 'Server WebSocket: attivo',
        'failed' => 'Server WebSocket: {detail}',
        'clientTesting' => 'Connessione del browser: verifica in corso…',
        'clientConnecting' => 'Connessione del browser: connessione in corso…',
        'clientConnected' => 'Connessione del browser: connessa',
        'clientDisconnecting' => 'Connessione del browser: disconnessione in corso…',
        'clientNotConnected' => 'Connessione del browser: non connessa',
    ],

    'UploadWorkerStatus' => [
        'running' => 'Servizio worker: attivo',
        'stopped' => 'Servizio worker: non attivo - i file in sospeso non verranno mai transcodificati finché non viene riavviato',
        'unknown' => 'Servizio worker: sconosciuto - systemctl potrebbe non essere disponibile su questo host, oppure SELinux sta negando la query di stato del server web stesso (esegui bin/install.php come root per risolverlo)',
        'queue' => 'Coda: {staging} in preparazione, {pending} in attesa, {processing} in elaborazione',
    ],

    'TrendingTimerStatus' => [
        'running' => 'Timer delle tendenze: attivo',
        'stopped' => 'Timer delle tendenze: non attivo - gli argomenti di tendenza si aggiorneranno solo tramite l\'autoripristino nel percorso di lettura (Trending::current()), non secondo una pianificazione. Esegui bin/install.php come root per configurarlo.',
        'unknown' => 'Timer delle tendenze: sconosciuto - systemctl potrebbe non essere disponibile su questo host, oppure SELinux sta negando la query di stato del server web stesso (esegui bin/install.php come root per risolverlo)',
    ],
];
