<?php

declare(strict_types=1);

/**
 * Italian for the notification and direct-messaging classes not already
 * covered by MessagingAndStatus.php. See src/locales/en/MessagingExtras.php
 * for what this fragment covers.
 */

return [
    'Notification' => [
        'postReady' => 'L\'elaborazione dei tuoi contenuti multimediali è terminata: ora sono pubblicati',
        'scheduledPostLive' => 'Il tuo post programmato è ora pubblicato',
        'uploadPartlyFailed' => 'Il tuo post è pubblicato, ma non è stato possibile elaborare uno o più dei suoi file',
        'uploadFailed' => 'Non è stato possibile elaborare uno dei tuoi file, che non è stato pubblicato',
        'mailerFailed' => 'Consegna email non riuscita - il servizio di posta potrebbe non essere attivo. Controlla la configurazione della posta.',
        'mailFromNotConfigured' => 'Nessun indirizzo email "mittente" è configurato, quindi non è possibile inviare email. Impostane uno in Impostazioni amministratore (sezione Posta in uscita) o tramite bin/install.php.',
        'systemError' => 'Si è verificato un errore del server. Controlla il registro degli errori per i dettagli.',
        // Matches the exact wording PageTitle.forgotPassword uses for this
        // same link, quoted here the way the English quotes "Forgot Password".
        'passwordRemovedGoogle' => 'La tua password è stata rimossa quando hai eseguito l\'accesso con Google. Usa "Password dimenticata" se vuoi impostarne una nuova.',
        // Passato prossimo with "avere" throughout this entry: it never agrees
        // with the subject's gender, so "{name} ha fatto X" reads correctly
        // whoever {name} is. "A {name} è piaciuto" (like) is the Italian
        // impersonal construction "piacere" always uses - the verb agrees
        // with "il tuo post" (the thing that pleased), not with {name}.
        'like' => 'A {name} è piaciuto il tuo post',
        'repost' => '{name} ha ripubblicato il tuo post',
        'reply' => '{name} ha risposto al tuo post',
        'friendRequest' => '{name} ti ha inviato una richiesta di amicizia',
        'friendAccepted' => '{name} ha accettato la tua richiesta di amicizia',
        'message' => '{name} ti ha inviato un messaggio',
        'mention' => '{name} ti ha menzionato in un post',
        'follow' => '{name} ha iniziato a seguirti da un altro server',
        'default' => '{name} ha fatto qualcosa',
    ],

    'NotificationList' => [
        'emptyNotice' => 'Ancora nessuna notifica.',
    ],

    'NotificationsNavLink' => [
        'label' => 'Notifiche',
        'unseen' => 'Notifiche non viste',
    ],

    'NotificationTestPanel' => [
        // "Inviati" (send yourself - a reflexive imperative) rather than
        // "te stesso/a": the verb form carries no gender, where the reflexive
        // pronoun phrase would.
        'intro' => 'Inviati una notifica di prova (l\'amministratore). Dovrebbe comparire immediatamente come toast e nel menu a discesa delle notifiche.',
        'button' => 'Invia notifica di prova',
        'sending' => 'Invio in corso…',
        'sent' => 'Inviata!',
        'failed' => 'Non riuscita',
    ],

    'MessageDot' => [
        'label' => 'Messaggi non letti',
    ],

    'NavAlertDot' => [
        'label' => 'Novità nel menu',
    ],

    'Message' => [
        'encrypted' => 'Messaggio cifrato',
        'decryptionFailed' => 'Questo messaggio è stato cifrato con chiavi che non esistono più.',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'Messaggio',
        'bodyPlaceholder' => 'Scrivi un messaggio',
        'send' => 'Invia',
    ],

    'MessageList' => [
        'emptyNotice' => 'Ancora nessun messaggio.',
    ],

    'MessageKeyFingerprint' => [
        // "Leggetevi... a vicenda" (voi, reciprocal) rather than the site's
        // usual "tu": this one sentence is inherently about two people acting
        // on each other, which "tu" cannot address on its own.
        'explanation' => 'Leggetevi questo codice a vicenda in un altro modo - ad alta voce, di persona, in una chiamata. Se corrisponde su entrambi i lati, tra voi non c\'è nessun altro.',
        'changed' => 'Questo codice è cambiato da quando l\'hai controllato. Succede quando uno di voi due reimposta le proprie chiavi di cifratura - ma è anche quello che si vedrebbe se qualcuno stesse leggendo questa conversazione. Verifica il nuovo codice con l\'altra persona prima di fidartene.',
        'verified' => 'Hai verificato questo codice.',
    ],

    'MessageKeyVerifyButton' => [
        'label' => 'Segna come verificato',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'Passphrase',
        'passphrasePlaceholder' => 'Passphrase per sbloccare questa conversazione',
        'submit' => 'Sblocca',
    ],

    'Conversation' => [
        'lastMessage' => ['before' => 'Ultimo messaggio ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => 'Contenuto sensibile',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => 'Mostra i contenuti sensibili per impostazione predefinita',
    ],
];
