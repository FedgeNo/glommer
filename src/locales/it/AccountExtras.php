<?php

declare(strict_types=1);

/**
 * Italian for the smaller account-related classes. See
 * src/locales/en/AccountExtras.php for what this fragment covers.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'Sito',
        'siteURLLabel' => 'URL del sito',
        'siteTitleLabel' => 'Titolo del sito',
        'mailFromAddressLabel' => 'Indirizzo email del mittente',
        'serverNameConfirmedLabel' => 'Ho impostato "ServerName {host}" e "UseCanonicalName On" nella configurazione del mio server web (controllato solo se il test automatico dal vivo non riesce a completarsi - vedi la sezione HTTPS del README.md)',
        'databaseLegend' => 'Database',
        'databaseHostLabel' => 'Host del database',
        'databasePortLabel' => 'Porta del database',
        'databaseNameLabel' => 'Nome del database',
        'databaseAdminUsernameLabel' => 'Nome utente amministratore del database',
        'databaseAdminPasswordLabel' => 'Password amministratore del database',
        'webSocketTLSLegend' => 'TLS per WebSocket (opzionale)',
        'certificatePathLabel' => 'Percorso del certificato',
        'certificatePathPlaceholder' => 'Lascia vuoto per generarlo automaticamente con mkcert',
        'keyPathLabel' => 'Percorso della chiave',
        'keyPathPlaceholder' => 'Lascia vuoto per generarla automaticamente con mkcert',
        'botProtectionLegend' => 'Protezione anti-bot (opzionale)',
        'turnstileSiteKeyLabel' => 'Chiave del sito Cloudflare Turnstile',
        'turnstileSiteKeyPlaceholder' => 'Lascia vuoto per saltare',
        'turnstileSecretKeyLabel' => 'Chiave segreta Cloudflare Turnstile',
        'turnstileSecretKeyPlaceholder' => 'Lascia vuoto per saltare',
        'submit' => 'Configura',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Passphrase attuale',
        'newPassphraseLabel' => 'Nuova passphrase',
        'confirmNewPassphraseLabel' => 'Conferma la nuova passphrase',
        'accountPasswordLabel' => 'Password dell\'account',
        'submit' => 'Cambia passphrase',
    ],

    'PasswordResetForm' => [
        'legend' => 'Scegli una nuova password',
        'newPasswordLabel' => 'Nuova password',
        'newPasswordPlaceholder' => 'Almeno 8 caratteri',
        'confirmPasswordLabel' => 'Conferma la nuova password',
        'submit' => 'Reimposta password',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Reimposta la tua password',
        'emailLabel' => 'Email',
        'submit' => 'Invia link di reimpostazione',
    ],

    'EmailRevertForm' => [
        'submit' => 'Annulla cambio email',
    ],

    'EmailVerifyForm' => [
        'submit' => 'Verifica indirizzo email',
    ],

    'EmailDigestResubscribeForm' => [
        // Named explicitly rather than a bare pronoun ("Riattivali" = "turn
        // them back on"): this button renders on unsubscribe.php, a page
        // whose title and surrounding paragraphs are hardcoded English
        // outside the Strings system, so there is no Italian "them" nearby
        // for a pronoun to lean on - see the German locale's identical fix
        // and its comment for why.
        'submit' => 'Riattiva i riepiloghi via email',
    ],

    'EmailDigestSetting' => [
        // Restructured around "ho perso" (perdere + avere) rather than the
        // idiomatic reflexive "mi sono perso" (perdersi + essere): the latter's
        // past participle would agree with the reader's own gender, which
        // nothing here knows. "ho perso" never inflects for the subject's
        // gender, so this reads the same regardless of who is reading it.
        'label' => 'Inviami un\'email con quello che ho perso quando sono assente da un po\'',
    ],

    'RememberedDevice' => [
        'unknownDevice' => 'Dispositivo sconosciuto',
        'browserOnOS' => '{browser} su {os}',
        'thisDevice' => ' (questo dispositivo)',
        'lastUsed' => ['before' => 'Ultimo utilizzo ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        // "Uscirai" rather than a passive "verrai disconnesso": the passive
        // participle would agree with the reader's own gender, and the plain
        // future tense of "uscire" carries no such agreement.
        'explanation' => 'Termina tutte le sessioni attive e dimentica tutti i dispositivi memorizzati. Uscirai da tutti i browser, incluso questo.',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'Esci ovunque',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Verifica con Google per eliminare',
    ],

    'GoogleSignInButton' => [
        'label' => 'Continua con Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Modifica profilo',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Ricevi notifiche su questo dispositivo anche quando il sito non è aperto. È una scelta specifica per ogni browser: attivala ovunque tu voglia essere raggiungibile.',
        'label' => [
            'off' => 'Attiva su questo dispositivo',
            'on' => 'Disattiva su questo dispositivo',
        ],
        'unsupported' => 'Le notifiche push non sono supportate su questo browser',
    ],
];
