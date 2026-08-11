<?php

declare(strict_types=1);

/**
 * German for the admin Site Settings forms. See
 * src/locales/en/AdminSettings.php for the source and the shape each entry is
 * built to.
 */

return [
    'MailSettingsForm' => [
        'legend' => 'Ausgehende E-Mail',
        'fromAddressLabel' => 'Absenderadresse',
        'fromAddressPlaceholder' => 'Es kann keine E-Mail gesendet werden, bis dies gesetzt ist',
        'fromNameLabel' => 'Absendername',
        'hostLabel' => 'SMTP-Host',
        'portLabel' => 'SMTP-Port',
        'usernameLabel' => 'SMTP-Benutzername',
        'passwordLabel' => 'SMTP-Passwort',
        'passwordPlaceholder' => [
            'set' => 'Passwort ist gesetzt - leer lassen, um es zu behalten',
            'unset' => 'SMTP-Passwort',
        ],
        'encryptionLabel' => 'Verschlüsselung',
        'encryptionOptions' => [
            'tls' => 'TLS (STARTTLS, üblich auf Port 587)',
            'ssl' => 'SSL (implizites TLS, üblich auf Port 465)',
            'none' => 'Keine',
        ],
        'explainer' => 'Lass den SMTP-Host leer, um stattdessen über PHPs mail() zu senden (nicht empfohlen - siehe den Abschnitt zur Zustellbarkeit in der README). Es kann überhaupt keine E-Mail gesendet werden, bevor eine Absenderadresse gesetzt ist.',
        'save' => 'Speichern',
    ],

    'MapSettingsForm' => [
        'legend' => 'Kartenkacheln',
        'notice' => 'Leer lassen, um OpenStreetMap zu verwenden. Um einen Anbieter mit Schlüssel zu nutzen, füge dessen URL-Vorlage ein, mit einem literalen {apiKey} an der Stelle des Schlüssels, und trage den Schlüssel sowie die Namensnennung unten ein.',
        'urlLabel' => 'URL-Vorlage der Kacheln',
        'keyLabel' => 'API-Schlüssel',
        'keyPlaceholder' => 'Der API-Schlüssel deines Kachel-Anbieters',
        'attributionLabel' => 'Namensnennung',
        'attributionPlaceholder' => '© OpenStreetMap-Mitwirkende',
        'save' => 'Speichern',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Google Sign-In',
        'clientIdLabel' => 'Client-ID',
        'clientIdPlaceholder' => 'Client-ID für Google OAuth',
        'secretLabel' => 'Client-Secret',
        'secretPlaceholder' => [
            'set' => 'Client-Secret ist gesetzt - leer lassen, um es zu behalten',
            'unset' => 'Client-Secret für Google OAuth',
        ],
        'explainer' => 'Beide werden benötigt, damit "Weiter mit Google" bei Registrierung und Anmeldung erscheint. Setze in deinem Google-Cloud-OAuth-Client die autorisierte Weiterleitungs-URI auf {url} - lösche die Client-ID, um es zu deaktivieren.',
        'save' => 'Speichern',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'Website-Schlüssel',
        'turnstileSiteKeyPlaceholder' => 'Website-Schlüssel für Cloudflare Turnstile',
        'turnstileSecretKeyLabel' => 'Geheimer Schlüssel',
        'turnstileSecretKeyPlaceholder' => [
            'set' => 'Geheimer Schlüssel ist gesetzt - leer lassen, um ihn zu behalten',
            'unset' => 'Geheimer Schlüssel für Cloudflare Turnstile',
        ],
        'turnstileExplainer' => 'Beide Schlüssel werden benötigt, damit das CAPTCHA bei Registrierung und Anmeldung erscheint. Lösche den Website-Schlüssel, um es zu deaktivieren.',
        'recaptchaLegend' => 'Google reCAPTCHA (Wiederherstellung bei Kontosperre)',
        'recaptchaSiteKeyLabel' => 'Website-Schlüssel',
        'recaptchaSiteKeyPlaceholder' => 'Website-Schlüssel für Google reCAPTCHA v2',
        'recaptchaSecretKeyLabel' => 'Geheimer Schlüssel',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => 'Geheimer Schlüssel ist gesetzt - leer lassen, um ihn zu behalten',
            'unset' => 'Geheimer Schlüssel für Google reCAPTCHA v2',
        ],
        'recaptchaExplainer' => 'Beide Schlüssel werden benötigt. Wenn gesetzt, kann sich ein Konto, das sein Limit an Anmeldeversuchen erreicht hat, durch Bestehen dieser Prüfung trotzdem anmelden, statt die Sperre auszusitzen; wenn nicht gesetzt, ist die Sperre eine feste Wartezeit. Verwende reCAPTCHA v2 ("Ich bin kein Roboter"). Lösche den Website-Schlüssel, um es zu deaktivieren.',
        'save' => 'Speichern',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'Wird von den KI-Funktionen der Seite verwendet (Zusammenfassungen von Trendthemen usw.). Lass das Modell leer, um den Free Models Router zu verwenden, den OpenRouter zufällig aus den aktuell kostenlosen Modellen auswählt und der niemals Kosten verursachen kann.',
        'keyLabel' => 'API-Schlüssel',
        'keyPlaceholder' => [
            'set' => 'API-Schlüssel ist gesetzt - leer lassen, um ihn zu behalten',
            'unset' => 'API-Schlüssel für OpenRouter',
        ],
        'clearKeyLabel' => 'Den gespeicherten API-Schlüssel entfernen (deaktiviert die KI-Funktionen)',
        'modelLabel' => 'Modell',
        'neverSpendLabel' => 'Niemals erlauben, dass dies Geld ausgibt (empfohlen)',
        'explainer' => 'Mit aktivierter Sperre begrenzt jede Anfrage ihren Preis auf null, sodass sie ganz fehlschlägt, statt auf ein kostenpflichtiges Modell auszuweichen, wenn kein kostenloser Anbieter verfügbar ist. Entferne den gespeicherten API-Schlüssel, um die KI-Funktionen vollständig zu deaktivieren.',
        'save' => 'Speichern',
    ],

    'AboutSettingsForm' => [
        'legend' => 'Über',
        'description' => 'Nur Text - Leerzeilen trennen Absätze. Der erste Absatz wird als Website-Beschreibung verwendet.',
        'save' => 'Speichern',
    ],

    'TermsSettingsForm' => [
        'legend' => 'Nutzungsbedingungen',
        'description' => 'Nur Text - Leerzeilen trennen Absätze.',
        'save' => 'Speichern',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'Datenschutzerklärung',
        'description' => 'Nur Text - Leerzeilen trennen Absätze.',
        'save' => 'Speichern',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'E-Mail-Zusammenfassung',
        'fieldLabel' => 'Schlussabsatz',
        'notice' => 'Wird am Ende jeder Zusammenfassung angehängt, nach der Liste dessen, was das Mitglied verpasst hat. Nur Text. Leer lassen, um zum mitgelieferten Standardtext dieser Software zurückzukehren.',
        'save' => 'Speichern',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'Favicon',
        'currentAlt' => 'Aktuelles Favicon',
        'save' => 'Favicon hochladen',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'Startseitenbild',
        'explainer' => 'Wird von anderen Seiten angezeigt, wenn jemand einen Link zu dieser hier teilt - nur Open-Graph-Metadaten, niemals auf der Seite selbst. Zugeschnitten auf 1200×630. Ohne dieses Bild haben Linkvorschauen gar kein Bild.',
        'currentAlt' => 'Aktuelles Startseitenbild',
        'save' => 'Bild hochladen',
    ],
];
