<?php

declare(strict_types=1);

/**
 * Italian for the Admin Settings forms. See src/locales/en/AdminSettings.php
 * for what this fragment covers.
 */

return [
    'MailSettingsForm' => [
        'legend' => 'Posta in uscita',
        'fromAddressLabel' => 'Indirizzo mittente',
        'fromAddressPlaceholder' => 'Non è possibile inviare email finché questo non viene impostato',
        'fromNameLabel' => 'Nome mittente',
        'hostLabel' => 'Host SMTP',
        'portLabel' => 'Porta SMTP',
        'usernameLabel' => 'Nome utente SMTP',
        'passwordLabel' => 'Password SMTP',
        'passwordPlaceholder' => [
            'set' => 'La password è impostata - lascia vuoto per mantenerla',
            'unset' => 'Password SMTP',
        ],
        'encryptionLabel' => 'Crittografia',
        'encryptionOptions' => [
            'tls' => 'TLS (STARTTLS, di solito sulla porta 587)',
            'ssl' => 'SSL (TLS implicito, di solito sulla porta 465)',
            'none' => 'Nessuna',
        ],
        'explainer' => 'Lascia vuoto l\'host SMTP per inviare invece tramite mail() di PHP (non consigliato - vedi la sezione sulla consegna del README). Non è possibile inviare alcuna email finché non viene impostato un indirizzo "mittente".',
        'save' => 'Salva',
    ],

    'MapSettingsForm' => [
        'legend' => 'Tessere della mappa',
        'notice' => 'Lascia vuoto per usare OpenStreetMap. Per usare un provider con chiave, incolla il suo modello di URL con un {apiKey} letterale dove deve andare la chiave, più la chiave e l\'attribuzione qui sotto.',
        'urlLabel' => 'Modello URL delle tessere',
        'keyLabel' => 'Chiave API',
        'keyPlaceholder' => 'La chiave API del tuo provider di tessere',
        'attributionLabel' => 'Attribuzione',
        'attributionPlaceholder' => '© OpenStreetMap contributors',
        'save' => 'Salva',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Accesso con Google',
        'clientIdLabel' => 'ID client',
        'clientIdPlaceholder' => 'ID client OAuth di Google',
        'secretLabel' => 'Segreto del client',
        'secretPlaceholder' => [
            'set' => 'Il segreto del client è impostato - lascia vuoto per mantenerlo',
            'unset' => 'Segreto del client OAuth di Google',
        ],
        'explainer' => 'Entrambi sono necessari perché "Continua con Google" compaia in fase di registrazione e accesso. Nel tuo client OAuth di Google Cloud, imposta l\'URI di reindirizzamento autorizzato su {url} - cancella l\'ID client per disattivarlo.',
        'save' => 'Salva',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'Chiave del sito',
        'turnstileSiteKeyPlaceholder' => 'Chiave del sito Cloudflare Turnstile',
        'turnstileSecretKeyLabel' => 'Chiave segreta',
        'turnstileSecretKeyPlaceholder' => [
            'set' => 'La chiave segreta è impostata - lascia vuoto per mantenerla',
            'unset' => 'Chiave segreta Cloudflare Turnstile',
        ],
        'turnstileExplainer' => 'Entrambe le chiavi sono necessarie perché il CAPTCHA compaia in fase di registrazione e accesso. Cancella la chiave del sito per disattivarlo.',
        'recaptchaLegend' => 'Google reCAPTCHA (recupero da blocco account)',
        'recaptchaSiteKeyLabel' => 'Chiave del sito',
        'recaptchaSiteKeyPlaceholder' => 'Chiave del sito reCAPTCHA v2 di Google',
        'recaptchaSecretKeyLabel' => 'Chiave segreta',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => 'La chiave segreta è impostata - lascia vuoto per mantenerla',
            'unset' => 'Chiave segreta reCAPTCHA v2 di Google',
        ],
        // "Non sono un robot" is what reCAPTCHA v2's own widget actually
        // displays in Italian, so this quote matches the real challenge text
        // rather than being an invented translation of it.
        'recaptchaExplainer' => 'Entrambe le chiavi sono necessarie. Se impostate, un account che raggiunge il proprio limite di tentativi di accesso può rientrare superando questa verifica invece di aspettare la fine del blocco; se non impostate, il blocco è un\'attesa obbligatoria. Usa reCAPTCHA v2 ("Non sono un robot"). Cancella la chiave del sito per disattivarlo.',
        'save' => 'Salva',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'Usato dalle funzioni IA del sito (riepiloghi degli argomenti di tendenza, ecc.). Lascia vuoto il modello per usare il Free Models Router, che OpenRouter sceglie a caso tra quelli attualmente gratuiti e che non può mai generare costi.',
        'keyLabel' => 'Chiave API',
        'keyPlaceholder' => [
            'set' => 'La chiave API è impostata - lascia vuoto per mantenerla',
            'unset' => 'Chiave API OpenRouter',
        ],
        'clearKeyLabel' => 'Rimuovi la chiave API salvata (disattiva le funzioni IA)',
        'modelLabel' => 'Modello',
        'neverSpendLabel' => 'Non permettere mai a questo di spendere denaro (consigliato)',
        'explainer' => 'Con questa protezione attiva, ogni richiesta limita il proprio prezzo a zero, quindi fallisce direttamente invece di ricorrere a un modello a pagamento quando non è disponibile alcun provider gratuito. Rimuovi la chiave API salvata per disattivare del tutto le funzioni IA.',
        'save' => 'Salva',
    ],

    'AboutSettingsForm' => [
        'legend' => 'Chi siamo',
        'description' => 'Testo semplice - le righe vuote separano i paragrafi. Il primo paragrafo verrà usato come descrizione del sito.',
        'save' => 'Salva',
    ],

    'TermsSettingsForm' => [
        'legend' => 'Termini di servizio',
        'description' => 'Testo semplice - le righe vuote separano i paragrafi.',
        'save' => 'Salva',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'Informativa sulla privacy',
        'description' => 'Testo semplice - le righe vuote separano i paragrafi.',
        'save' => 'Salva',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'Riepilogo via email',
        'fieldLabel' => 'Paragrafo finale',
        // "ha perso" rather than the reflexive "si è perso": the member's
        // gender is unknown, and "perdere" with "avere" never agrees with it -
        // see AccountExtras.php's EmailDigestSetting for the same fix.
        'notice' => 'Aggiunto verso la fine di ogni riepilogo, dopo l\'elenco di ciò che il membro ha perso. Testo semplice. Lascialo vuoto per tornare al testo predefinito di questo software.',
        'save' => 'Salva',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'Favicon',
        'currentAlt' => 'Favicon attuale',
        'save' => 'Carica favicon',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'Immagine della homepage',
        'explainer' => 'Mostrata da altri siti quando qualcuno condivide un link a questo - solo metadati Open Graph, mai sulla pagina. Ritagliata a 1200×630. Senza di essa, le anteprime dei link non hanno alcuna immagine.',
        'currentAlt' => 'Immagine attuale della homepage',
        'save' => 'Carica immagine',
    ],
];
