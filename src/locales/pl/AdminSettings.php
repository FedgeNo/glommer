<?php

declare(strict_types=1);

/**
 * Polish for the Admin Settings forms. See src/locales/en/AdminSettings.php
 * for what each of these is.
 */

return [
    'MailSettingsForm' => [
        'legend' => 'Poczta wychodząca',
        'fromAddressLabel' => 'Adres nadawcy',
        'fromAddressPlaceholder' => 'Żaden e-mail nie zostanie wysłany, dopóki to nie zostanie ustawione',
        'fromNameLabel' => 'Nazwa nadawcy',
        'hostLabel' => 'Host SMTP',
        'portLabel' => 'Port SMTP',
        'usernameLabel' => 'Nazwa użytkownika SMTP',
        'passwordLabel' => 'Hasło SMTP',
        'passwordPlaceholder' => [
            'set' => 'Hasło jest ustawione - pozostaw puste, aby je zachować',
            'unset' => 'Hasło SMTP',
        ],
        'encryptionLabel' => 'Szyfrowanie',
        'encryptionOptions' => [
            'tls' => 'TLS (STARTTLS, zwykle na porcie 587)',
            'ssl' => 'SSL (niejawny TLS, zwykle na porcie 465)',
            'none' => 'Brak',
        ],
        'explainer' => 'Pozostaw host SMTP pusty, aby zamiast tego wysyłać przez funkcję mail() PHP (niezalecane - zobacz sekcję README o dostarczalności). Żaden e-mail nie zostanie wysłany, dopóki nie zostanie ustawiony adres nadawcy.',
        'save' => 'Zapisz',
    ],

    'MapSettingsForm' => [
        'legend' => 'Kafelki mapy',
        'notice' => 'Pozostaw puste, aby użyć OpenStreetMap. Aby użyć dostawcy wymagającego klucza, wklej jego szablon URL z dosłownym {apiKey} w miejscu, gdzie ma trafić klucz, a klucz i informację o źródle podaj poniżej.',
        'urlLabel' => 'Szablon URL kafelków',
        'keyLabel' => 'Klucz API',
        'keyPlaceholder' => 'Klucz API twojego dostawcy kafelków',
        'attributionLabel' => 'Informacja o źródle',
        'attributionPlaceholder' => '© OpenStreetMap contributors',
        'save' => 'Zapisz',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Logowanie przez Google',
        'clientIdLabel' => 'Identyfikator klienta',
        'clientIdPlaceholder' => 'Identyfikator klienta OAuth Google',
        'secretLabel' => 'Tajny klucz klienta',
        'secretPlaceholder' => [
            'set' => 'Tajny klucz klienta jest ustawiony - pozostaw puste, aby go zachować',
            'unset' => 'Tajny klucz klienta OAuth Google',
        ],
        'explainer' => 'Oba pola są wymagane, aby przycisk "Kontynuuj przez Google" pojawiał się przy rejestracji i logowaniu. W kliencie OAuth Google Cloud ustaw autoryzowany URI przekierowania na {url} - wyczyść identyfikator klienta, aby to wyłączyć.',
        'save' => 'Zapisz',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'Klucz witryny',
        'turnstileSiteKeyPlaceholder' => 'Klucz witryny Cloudflare Turnstile',
        'turnstileSecretKeyLabel' => 'Klucz tajny',
        'turnstileSecretKeyPlaceholder' => [
            'set' => 'Klucz tajny jest ustawiony - pozostaw puste, aby go zachować',
            'unset' => 'Klucz tajny Cloudflare Turnstile',
        ],
        'turnstileExplainer' => 'Oba klucze są wymagane, aby CAPTCHA pojawiała się przy rejestracji i logowaniu. Wyczyść klucz witryny, aby ją wyłączyć.',
        'recaptchaLegend' => 'Google reCAPTCHA (odzyskiwanie zablokowanego konta)',
        'recaptchaSiteKeyLabel' => 'Klucz witryny',
        'recaptchaSiteKeyPlaceholder' => 'Klucz witryny Google reCAPTCHA v2',
        'recaptchaSecretKeyLabel' => 'Klucz tajny',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => 'Klucz tajny jest ustawiony - pozostaw puste, aby go zachować',
            'unset' => 'Klucz tajny Google reCAPTCHA v2',
        ],
        'recaptchaExplainer' => 'Oba klucze są wymagane. Gdy są ustawione, konto, które osiągnie limit prób logowania, może się dostać z powrotem, przechodząc to wyzwanie zamiast czekać na koniec blokady; gdy nie są ustawione, blokada oznacza sztywne oczekiwanie. Użyj reCAPTCHA v2 ("Nie jestem robotem"). Wyczyść klucz witryny, aby to wyłączyć.',
        'save' => 'Zapisz',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'Używane przez funkcje AI na stronie (podsumowania popularnych tematów itp.). Pozostaw model pusty, aby użyć Free Models Router, który OpenRouter losowo wybiera spośród aktualnie darmowych modeli i który nigdy nie generuje kosztów.',
        'keyLabel' => 'Klucz API',
        'keyPlaceholder' => [
            'set' => 'Klucz API jest ustawiony - pozostaw puste, aby go zachować',
            'unset' => 'Klucz API OpenRouter',
        ],
        'clearKeyLabel' => 'Usuń zapisany klucz API (wyłącza funkcje AI)',
        'modelLabel' => 'Model',
        'neverSpendLabel' => 'Nigdy nie pozwalaj na wydawanie pieniędzy (zalecane)',
        'explainer' => 'Przy włączonym zabezpieczeniu każde żądanie ma cenę ograniczoną do zera, więc kończy się niepowodzeniem zamiast przechodzić na płatny model, gdy żaden darmowy dostawca nie jest dostępny. Usuń zapisany klucz API, aby całkowicie wyłączyć funkcje AI.',
        'save' => 'Zapisz',
    ],

    'AboutSettingsForm' => [
        'legend' => 'O witrynie',
        'description' => 'Zwykły tekst - puste wiersze oddzielają akapity. Pierwszy akapit będzie używany jako opis witryny.',
        'save' => 'Zapisz',
    ],

    'TermsSettingsForm' => [
        'legend' => 'Regulamin',
        'description' => 'Zwykły tekst - puste wiersze oddzielają akapity.',
        'save' => 'Zapisz',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'Polityka prywatności',
        'description' => 'Zwykły tekst - puste wiersze oddzielają akapity.',
        'save' => 'Zapisz',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'Podsumowanie e-mailowe',
        'fieldLabel' => 'Akapit końcowy',
        'notice' => 'Dodawany przy końcu każdego podsumowania, po liście tego, co przegapił użytkownik. Zwykły tekst. Pozostaw puste, aby wrócić do treści domyślnie dostarczanej z oprogramowaniem.',
        'save' => 'Zapisz',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'Favicon',
        'currentAlt' => 'Aktualna favicon',
        'save' => 'Prześlij favicon',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'Obraz strony głównej',
        'explainer' => 'Wyświetlany przez inne witryny, gdy ktoś udostępni link do tej strony - wyłącznie metadane Open Graph, nigdy na samej stronie. Przycinany do 1200×630. Bez niego podglądy linków nie zawierają żadnego obrazu.',
        'currentAlt' => 'Obecny obraz strony głównej',
        'save' => 'Prześlij obraz',
    ],
];
