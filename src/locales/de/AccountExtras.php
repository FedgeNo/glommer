<?php

declare(strict_types=1);

/**
 * German for the smaller account-related classes. See
 * src/locales/en/AccountExtras.php for the source and the shape each entry is
 * built to.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'Website',
        'siteURLLabel' => 'Website-URL',
        'siteTitleLabel' => 'Website-Titel',
        'mailFromAddressLabel' => 'Absenderadresse',
        'serverNameConfirmedLabel' => 'Ich habe "ServerName {host}" und "UseCanonicalName On" in der Konfiguration meines Webservers gesetzt (wird nur geprüft, wenn der automatische Live-Test nicht abgeschlossen werden kann - siehe den HTTPS-Abschnitt in README.md)',
        'databaseLegend' => 'Datenbank',
        'databaseHostLabel' => 'Datenbank-Host',
        'databasePortLabel' => 'Datenbank-Port',
        'databaseNameLabel' => 'Datenbankname',
        'databaseAdminUsernameLabel' => 'Datenbank-Admin-Benutzername',
        'databaseAdminPasswordLabel' => 'Datenbank-Admin-Passwort',
        'webSocketTLSLegend' => 'WebSocket-TLS (optional)',
        'certificatePathLabel' => 'Zertifikatspfad',
        'certificatePathPlaceholder' => 'Leer lassen, um es automatisch über mkcert zu erzeugen',
        'keyPathLabel' => 'Schlüsselpfad',
        'keyPathPlaceholder' => 'Leer lassen, um ihn automatisch über mkcert zu erzeugen',
        'botProtectionLegend' => 'Bot-Schutz (optional)',
        'turnstileSiteKeyLabel' => 'Website-Schlüssel für Cloudflare Turnstile',
        'turnstileSiteKeyPlaceholder' => 'Leer lassen, um zu überspringen',
        'turnstileSecretKeyLabel' => 'Geheimer Schlüssel für Cloudflare Turnstile',
        'turnstileSecretKeyPlaceholder' => 'Leer lassen, um zu überspringen',
        'submit' => 'Einrichten',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Aktuelle Passphrase',
        'newPassphraseLabel' => 'Neue Passphrase',
        'confirmNewPassphraseLabel' => 'Neue Passphrase bestätigen',
        'accountPasswordLabel' => 'Kontopasswort',
        'submit' => 'Passphrase ändern',
    ],

    'PasswordResetForm' => [
        'legend' => 'Neues Passwort wählen',
        'newPasswordLabel' => 'Neues Passwort',
        'newPasswordPlaceholder' => 'Mindestens 8 Zeichen',
        'confirmPasswordLabel' => 'Neues Passwort bestätigen',
        'submit' => 'Passwort zurücksetzen',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Passwort zurücksetzen',
        'emailLabel' => 'E-Mail',
        'submit' => 'Link zum Zurücksetzen senden',
    ],

    'EmailRevertForm' => [
        'submit' => 'E-Mail-Änderung rückgängig machen',
    ],

    'EmailVerifyForm' => [
        'submit' => 'E-Mail-Adresse bestätigen',
    ],

    'EmailDigestResubscribeForm' => [
        // Named explicitly rather than 'Wieder aktivieren' alone: this button
        // renders on unsubscribe.php, a page whose title and surrounding
        // paragraphs are hardcoded English outside the Strings system, so
        // there is no German "them" nearby for a bare pronoun to lean on.
        'submit' => 'E-Mail-Zusammenfassungen wieder aktivieren',
    ],

    'EmailDigestSetting' => [
        'label' => 'Schick mir per E-Mail, was ich verpasst habe, wenn ich eine Weile weg war',
    ],

    'RememberedDevice' => [
        'unknownDevice' => 'Unbekanntes Gerät',
        'browserOnOS' => '{browser} unter {os}',
        'thisDevice' => ' (dieses Gerät)',
        'lastUsed' => ['before' => 'Zuletzt verwendet ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        'explanation' => 'Beendet jede aktive Sitzung und vergisst jedes gemerkte Gerät. Du wirst in allen Browsern abgemeldet, auch in diesem hier.',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'Überall abmelden',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Zum Löschen mit Google bestätigen',
    ],

    'GoogleSignInButton' => [
        'label' => 'Weiter mit Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Profil bearbeiten',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Erhalte Benachrichtigungen auf diesem Gerät, auch wenn die Seite nicht geöffnet ist. Das ist eine Einstellung pro Browser - aktiviere sie überall, wo du erreichbar sein willst.',
        'label' => [
            'off' => 'Auf diesem Gerät aktivieren',
            'on' => 'Auf diesem Gerät deaktivieren',
        ],
        'unsupported' => 'Push wird in diesem Browser nicht unterstützt',
    ],
];
