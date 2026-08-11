<?php

declare(strict_types=1);

/**
 * German for the notification and direct-messaging classes not already
 * covered by MessagingAndStatus.php. See src/locales/en/MessagingExtras.php
 * for the source and the shape each entry is built to.
 */

return [
    'Notification' => [
        'postReady' => 'Deine Medien wurden fertig verarbeitet und sind jetzt live',
        'scheduledPostLive' => 'Dein geplanter Beitrag ist jetzt live',
        'uploadPartlyFailed' => 'Dein Beitrag ist live, aber eine oder mehrere seiner Dateien konnten nicht verarbeitet werden',
        'uploadFailed' => 'Einer deiner Uploads konnte nicht verarbeitet werden und wurde nicht veröffentlicht',
        'mailerFailed' => 'E-Mail-Zustellung fehlgeschlagen - der Mailer könnte nicht erreichbar sein. Bitte überprüfe deine Mail-Konfiguration.',
        'mailFromNotConfigured' => 'Es ist keine Absenderadresse konfiguriert, daher können keine E-Mails gesendet werden. Lege eine in den Admin-Einstellungen (Bereich "Ausgehende E-Mail") fest oder über bin/install.php.',
        'systemError' => 'Ein Serverfehler ist aufgetreten. Prüfe das Fehlerprotokoll für Details.',
        'passwordRemovedGoogle' => 'Dein Passwort wurde entfernt, als du dich mit Google angemeldet hast. Nutze "Passwort vergessen", wenn du ein neues setzen möchtest.',
        'like' => '{name} gefällt dein Beitrag',
        'repost' => '{name} hat deinen Beitrag geteilt',
        'reply' => '{name} hat auf deinen Beitrag geantwortet',
        'friendRequest' => '{name} hat dir eine Freundschaftsanfrage geschickt',
        'friendAccepted' => '{name} hat deine Freundschaftsanfrage angenommen',
        'message' => '{name} hat dir eine Nachricht geschickt',
        'mention' => '{name} hat dich in einem Beitrag erwähnt',
        'follow' => '{name} ist dir von einem anderen Server aus gefolgt',
        'default' => '{name} hat etwas getan',
    ],

    'NotificationList' => [
        'emptyNotice' => 'Noch keine Benachrichtigungen.',
    ],

    'NotificationsNavLink' => [
        'label' => 'Benachrichtigungen',
        'unseen' => 'Ungelesene Benachrichtigungen',
    ],

    'NotificationTestPanel' => [
        'intro' => 'Sendet eine Testbenachrichtigung an dich selbst (die Administration). Sie sollte sofort als Toast und im Benachrichtigungsmenü erscheinen.',
        'button' => 'Testbenachrichtigung senden',
        'sending' => 'Wird gesendet…',
        'sent' => 'Gesendet!',
        'failed' => 'Fehlgeschlagen',
    ],

    'MessageDot' => [
        'label' => 'Ungelesene Nachrichten',
    ],

    'NavAlertDot' => [
        'label' => 'Etwas Neues im Menü',
    ],

    'Message' => [
        'encrypted' => 'Verschlüsselte Nachricht',
        'decryptionFailed' => 'Diese Nachricht wurde mit Schlüsseln verschlüsselt, die nicht mehr existieren.',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'Nachricht',
        'bodyPlaceholder' => 'Schreib eine Nachricht',
        'send' => 'Senden',
    ],

    'MessageList' => [
        'emptyNotice' => 'Noch keine Nachrichten.',
    ],

    'MessageKeyFingerprint' => [
        'explanation' => 'Lest euch diesen Code auf einem anderen Weg gegenseitig vor - laut, persönlich, in einem Anruf. Wenn er auf beiden Seiten übereinstimmt, ist niemand zwischen euch.',
        'changed' => 'Dieser Code hat sich geändert, seit du ihn geprüft hast. Das passiert, wenn einer von euch seine Verschlüsselungsschlüssel zurücksetzt - aber genauso würde es aussehen, wenn jemand diese Unterhaltung mitliest. Prüfe den neuen Code mit der anderen Person, bevor du ihm vertraust.',
        'verified' => 'Du hast diesen Code geprüft.',
    ],

    'MessageKeyVerifyButton' => [
        'label' => 'Als geprüft markieren',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'Passphrase',
        'passphrasePlaceholder' => 'Passphrase, um diese Unterhaltung zu entsperren',
        'submit' => 'Entsperren',
    ],

    'Conversation' => [
        'lastMessage' => ['before' => 'Letzte Nachricht ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => 'Sensible Medien',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => 'Sensible Medien standardmäßig anzeigen',
    ],
];
