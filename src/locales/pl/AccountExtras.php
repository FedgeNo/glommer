<?php

declare(strict_types=1);

/**
 * Polish for the smaller account-related classes. See
 * src/locales/en/AccountExtras.php for what each of these is.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'Witryna',
        'siteURLLabel' => 'Adres URL witryny',
        'siteTitleLabel' => 'Tytuł witryny',
        'mailFromAddressLabel' => 'Adres e-mail nadawcy',
        'serverNameConfirmedLabel' => 'Mam ustawione "ServerName {host}" oraz "UseCanonicalName On" w konfiguracji mojego serwera WWW (sprawdzane tylko wtedy, gdy automatyczny test na żywo nie może się powieść - zobacz sekcję HTTPS w README.md)',
        'databaseLegend' => 'Baza danych',
        'databaseHostLabel' => 'Host bazy danych',
        'databasePortLabel' => 'Port bazy danych',
        'databaseNameLabel' => 'Nazwa bazy danych',
        'databaseAdminUsernameLabel' => 'Nazwa użytkownika administratora bazy danych',
        'databaseAdminPasswordLabel' => 'Hasło administratora bazy danych',
        'webSocketTLSLegend' => 'TLS dla WebSocket (opcjonalnie)',
        'certificatePathLabel' => 'Ścieżka do certyfikatu',
        'certificatePathPlaceholder' => 'Pozostaw puste, aby wygenerować automatycznie przez mkcert',
        'keyPathLabel' => 'Ścieżka do klucza',
        'keyPathPlaceholder' => 'Pozostaw puste, aby wygenerować automatycznie przez mkcert',
        'botProtectionLegend' => 'Ochrona przed botami (opcjonalnie)',
        'turnstileSiteKeyLabel' => 'Klucz witryny Cloudflare Turnstile',
        'turnstileSiteKeyPlaceholder' => 'Pozostaw puste, aby pominąć',
        'turnstileSecretKeyLabel' => 'Klucz tajny Cloudflare Turnstile',
        'turnstileSecretKeyPlaceholder' => 'Pozostaw puste, aby pominąć',
        'submit' => 'Skonfiguruj',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Obecne hasło szyfrujące',
        'newPassphraseLabel' => 'Nowe hasło szyfrujące',
        'confirmNewPassphraseLabel' => 'Potwierdź nowe hasło szyfrujące',
        'accountPasswordLabel' => 'Hasło do konta',
        'submit' => 'Zmień hasło szyfrujące',
    ],

    'PasswordResetForm' => [
        'legend' => 'Wybierz nowe hasło',
        'newPasswordLabel' => 'Nowe hasło',
        'newPasswordPlaceholder' => 'Co najmniej 8 znaków',
        'confirmPasswordLabel' => 'Potwierdź nowe hasło',
        'submit' => 'Zresetuj hasło',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Zresetuj hasło',
        'emailLabel' => 'E-mail',
        'submit' => 'Wyślij link resetujący',
    ],

    'EmailRevertForm' => [
        'submit' => 'Cofnij zmianę e-maila',
    ],

    'EmailVerifyForm' => [
        'submit' => 'Zweryfikuj adres e-mail',
    ],

    'EmailDigestResubscribeForm' => [
        'submit' => 'Włącz je ponownie',
    ],

    'EmailDigestSetting' => [
        'label' => 'Wysyłaj mi e-mailem to, co mnie ominęło, gdy dłużej mnie nie było',
    ],

    'RememberedDevice' => [
        'unknownDevice' => 'Nieznane urządzenie',
        'browserOnOS' => '{browser} na {os}',
        'thisDevice' => ' (to urządzenie)',
        'lastUsed' => ['before' => 'Ostatnio użyte ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        'explanation' => 'Zakończ wszystkie aktywne sesje i zapomnij wszystkie zapamiętane urządzenia. Nastąpi wylogowanie ze wszystkich przeglądarek, łącznie z tą.',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'Wyloguj wszędzie',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Zweryfikuj przez Google, aby usunąć',
    ],

    'GoogleSignInButton' => [
        'label' => 'Kontynuuj przez Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Edytuj profil',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Otrzymuj powiadomienia na tym urządzeniu, nawet gdy witryna nie jest otwarta. To ustawienie dotyczy każdej przeglądarki z osobna - włącz je wszędzie tam, gdzie chcesz je otrzymywać.',
        'label' => [
            'off' => 'Włącz na tym urządzeniu',
            'on' => 'Wyłącz na tym urządzeniu',
        ],
        'unsupported' => 'Powiadomienia push nie są obsługiwane w tej przeglądarce',
    ],
];
