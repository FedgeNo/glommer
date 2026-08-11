<?php

declare(strict_types=1);

/**
 * Dutch for the smaller account-related classes. See
 * src/locales/en/AccountExtras.php for the source and the shape each entry is
 * built to.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'Website',
        'siteURLLabel' => 'Website-URL',
        'siteTitleLabel' => 'Websitetitel',
        'mailFromAddressLabel' => 'Afzendadres',
        'serverNameConfirmedLabel' => 'Ik heb "ServerName {host}" en "UseCanonicalName On" ingesteld in de configuratie van mijn webserver (wordt alleen gecontroleerd als de geautomatiseerde livetest niet kan worden voltooid - zie de HTTPS-sectie van README.md)',
        'databaseLegend' => 'Database',
        'databaseHostLabel' => 'Databasehost',
        'databasePortLabel' => 'Databasepoort',
        'databaseNameLabel' => 'Databasenaam',
        'databaseAdminUsernameLabel' => 'Gebruikersnaam databasebeheerder',
        'databaseAdminPasswordLabel' => 'Wachtwoord databasebeheerder',
        'webSocketTLSLegend' => 'WebSocket-TLS (optioneel)',
        'certificatePathLabel' => 'Certificaatpad',
        'certificatePathPlaceholder' => 'Laat leeg om automatisch te genereren via mkcert',
        'keyPathLabel' => 'Sleutelpad',
        'keyPathPlaceholder' => 'Laat leeg om automatisch te genereren via mkcert',
        'botProtectionLegend' => 'Botbescherming (optioneel)',
        'turnstileSiteKeyLabel' => 'Sitesleutel voor Cloudflare Turnstile',
        'turnstileSiteKeyPlaceholder' => 'Laat leeg om over te slaan',
        'turnstileSecretKeyLabel' => 'Geheime sleutel voor Cloudflare Turnstile',
        'turnstileSecretKeyPlaceholder' => 'Laat leeg om over te slaan',
        'submit' => 'Instellen',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Huidige wachtwoordzin',
        'newPassphraseLabel' => 'Nieuwe wachtwoordzin',
        'confirmNewPassphraseLabel' => 'Bevestig nieuwe wachtwoordzin',
        'accountPasswordLabel' => 'Accountwachtwoord',
        'submit' => 'Wachtwoordzin wijzigen',
    ],

    'PasswordResetForm' => [
        'legend' => 'Kies een nieuw wachtwoord',
        'newPasswordLabel' => 'Nieuw wachtwoord',
        'newPasswordPlaceholder' => 'Minstens 8 tekens',
        'confirmPasswordLabel' => 'Bevestig nieuw wachtwoord',
        'submit' => 'Wachtwoord opnieuw instellen',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Stel je wachtwoord opnieuw in',
        'emailLabel' => 'E-mail',
        'submit' => 'Resetlink versturen',
    ],

    'EmailRevertForm' => [
        'submit' => 'E-mailwijziging terugdraaien',
    ],

    'EmailVerifyForm' => [
        'submit' => 'E-mailadres verifiëren',
    ],

    'EmailDigestResubscribeForm' => [
        // Named explicitly rather than a bare pronoun: this button renders on
        // unsubscribe.php, whose surrounding title and paragraphs are
        // hardcoded English outside the Strings system, so there is no Dutch
        // "them" nearby for a pronoun to lean on.
        'submit' => 'E-mailoverzichten weer inschakelen',
    ],

    'EmailDigestSetting' => [
        'label' => 'Mail me wat ik heb gemist als ik een tijdje weg ben geweest',
    ],

    'RememberedDevice' => [
        'unknownDevice' => 'Onbekend apparaat',
        'browserOnOS' => '{browser} op {os}',
        'thisDevice' => ' (dit apparaat)',
        'lastUsed' => ['before' => 'Laatst gebruikt ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        'explanation' => 'Beëindigt elke actieve sessie en vergeet elk onthouden apparaat. Je wordt uitgelogd op alle browsers, ook deze.',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'Overal uitloggen',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Verifiëren met Google om te verwijderen',
    ],

    'GoogleSignInButton' => [
        'label' => 'Doorgaan met Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Profiel bewerken',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Ontvang meldingen op dit apparaat, ook als de site niet open staat. Dit is een keuze per browser - zet het aan overal waar je bereikbaar wilt zijn.',
        'label' => [
            'off' => 'Inschakelen op dit apparaat',
            'on' => 'Uitschakelen op dit apparaat',
        ],
        'unsupported' => 'Push wordt niet ondersteund in deze browser',
    ],
];
