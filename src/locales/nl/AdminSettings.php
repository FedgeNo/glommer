<?php

declare(strict_types=1);

/**
 * Dutch for the Admin Settings forms. See
 * src/locales/en/AdminSettings.php for the source and the shape each entry is
 * built to.
 */

return [
    'MailSettingsForm' => [
        'legend' => 'Uitgaande e-mail',
        'fromAddressLabel' => 'Afzendadres',
        'fromAddressPlaceholder' => 'Er kan geen e-mail worden verstuurd totdat dit is ingesteld',
        'fromNameLabel' => 'Afzendnaam',
        'hostLabel' => 'SMTP-host',
        'portLabel' => 'SMTP-poort',
        'usernameLabel' => 'SMTP-gebruikersnaam',
        'passwordLabel' => 'SMTP-wachtwoord',
        'passwordPlaceholder' => [
            'set' => 'Wachtwoord is ingesteld - laat leeg om het te behouden',
            'unset' => 'SMTP-wachtwoord',
        ],
        'encryptionLabel' => 'Versleuteling',
        'encryptionOptions' => [
            'tls' => 'TLS (STARTTLS, gebruikelijk op poort 587)',
            'ssl' => 'SSL (impliciete TLS, gebruikelijk op poort 465)',
            'none' => 'Geen',
        ],
        'explainer' => 'Laat de SMTP-host leeg om in plaats daarvan te versturen via de mail() van PHP (niet aanbevolen - zie de sectie over aflevering in de README). Er kan pas e-mail worden verstuurd zodra een "afzend"-adres is ingesteld.',
        'save' => 'Opslaan',
    ],

    'MapSettingsForm' => [
        'legend' => 'Kaarttegels',
        'notice' => 'Laat leeg om OpenStreetMap te gebruiken. Wil je een provider met een sleutel gebruiken, plak dan de URL-sjabloon met een letterlijke {apiKey} op de plek van de sleutel, plus de sleutel en bronvermelding hieronder.',
        'urlLabel' => 'URL-sjabloon voor tegels',
        'keyLabel' => 'API-sleutel',
        'keyPlaceholder' => 'De API-sleutel van je tegelprovider',
        'attributionLabel' => 'Bronvermelding',
        'attributionPlaceholder' => '© OpenStreetMap contributors',
        'save' => 'Opslaan',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Inloggen met Google',
        'clientIdLabel' => 'Client-ID',
        'clientIdPlaceholder' => 'Google OAuth-client-ID',
        'secretLabel' => 'Clientgeheim',
        'secretPlaceholder' => [
            'set' => 'Clientgeheim is ingesteld - laat leeg om het te behouden',
            'unset' => 'Google OAuth-clientgeheim',
        ],
        'explainer' => 'Beide zijn vereist om "Doorgaan met Google" te laten verschijnen bij registreren en inloggen. Stel in je Google Cloud OAuth-client de geautoriseerde redirect-URI in op {url} - maak het Client-ID leeg om het uit te schakelen.',
        'save' => 'Opslaan',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'Sitesleutel',
        'turnstileSiteKeyPlaceholder' => 'Sitesleutel voor Cloudflare Turnstile',
        'turnstileSecretKeyLabel' => 'Geheime sleutel',
        'turnstileSecretKeyPlaceholder' => [
            'set' => 'Geheime sleutel is ingesteld - laat leeg om deze te behouden',
            'unset' => 'Geheime sleutel voor Cloudflare Turnstile',
        ],
        'turnstileExplainer' => 'Beide sleutels zijn vereist om de CAPTCHA te laten verschijnen bij registreren en inloggen. Maak de sitesleutel leeg om het uit te schakelen.',
        'recaptchaLegend' => 'Google reCAPTCHA (herstel bij accountvergrendeling)',
        'recaptchaSiteKeyLabel' => 'Sitesleutel',
        'recaptchaSiteKeyPlaceholder' => 'Sitesleutel voor Google reCAPTCHA v2',
        'recaptchaSecretKeyLabel' => 'Geheime sleutel',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => 'Geheime sleutel is ingesteld - laat leeg om deze te behouden',
            'unset' => 'Geheime sleutel voor Google reCAPTCHA v2',
        ],
        'recaptchaExplainer' => 'Beide sleutels zijn vereist. Indien ingesteld, kan een account dat zijn limiet voor inlogpogingen heeft bereikt weer naar binnen door deze uitdaging te doorstaan in plaats van de vergrendeling uit te zitten; indien niet ingesteld, is de vergrendeling een harde wachttijd. Gebruik reCAPTCHA v2 ("Ik ben geen robot"). Maak de sitesleutel leeg om het uit te schakelen.',
        'save' => 'Opslaan',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'Gebruikt door AI-functies op de site (samenvattingen van trending topics, enz.). Laat het model leeg om de Free Models Router te gebruiken, die OpenRouter willekeurig kiest uit wat op dat moment gratis is en nooit kosten met zich mee kan brengen.',
        'keyLabel' => 'API-sleutel',
        'keyPlaceholder' => [
            'set' => 'API-sleutel is ingesteld - laat leeg om deze te behouden',
            'unset' => 'OpenRouter API-sleutel',
        ],
        'clearKeyLabel' => 'Verwijder de opgeslagen API-sleutel (schakelt AI-functies uit)',
        'modelLabel' => 'Model',
        'neverSpendLabel' => 'Sta nooit toe dat dit geld uitgeeft (aanbevolen)',
        'explainer' => 'Met deze beveiliging aan is de prijs van elk verzoek gemaximeerd op nul, zodat het volledig mislukt in plaats van terug te vallen op een betaald model als er geen gratis provider beschikbaar is. Verwijder de opgeslagen API-sleutel om AI-functies volledig uit te schakelen.',
        'save' => 'Opslaan',
    ],

    'AboutSettingsForm' => [
        'legend' => 'Over',
        'description' => 'Platte tekst - lege regels scheiden alinea\'s. De eerste alinea wordt gebruikt als sitebeschrijving.',
        'save' => 'Opslaan',
    ],

    'TermsSettingsForm' => [
        'legend' => 'Gebruiksvoorwaarden',
        'description' => 'Platte tekst - lege regels scheiden alinea\'s.',
        'save' => 'Opslaan',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'Privacybeleid',
        'description' => 'Platte tekst - lege regels scheiden alinea\'s.',
        'save' => 'Opslaan',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'E-mailoverzicht',
        'fieldLabel' => 'Afsluitende alinea',
        'notice' => 'Wordt toegevoegd vlak voor het einde van elk overzicht, na de lijst met wat het lid heeft gemist. Platte tekst. Laat leeg om terug te gaan naar de tekst die standaard bij deze software hoort.',
        'save' => 'Opslaan',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'Favicon',
        'currentAlt' => 'Huidige favicon',
        'save' => 'Favicon uploaden',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'Afbeelding voorpagina',
        'explainer' => 'Wordt getoond door andere sites wanneer iemand een link naar deze site deelt - alleen Open Graph-metadata, nooit op de pagina zelf. Bijgesneden tot 1200×630. Zonder afbeelding bevatten linkvoorvertoningen helemaal geen afbeelding.',
        'currentAlt' => 'Huidige afbeelding voorpagina',
        'save' => 'Afbeelding uploaden',
    ],
];
