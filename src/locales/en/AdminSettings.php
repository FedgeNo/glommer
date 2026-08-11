<?php

declare(strict_types=1);

/**
 * The words the admin Site Settings forms say, in English. See
 * src/locales/en.php for what this file is and why it exists apart from it.
 */

return [
    'MailSettingsForm' => [
        'legend' => 'Outgoing mail',
        'fromAddressLabel' => 'From address',
        'fromAddressPlaceholder' => 'No email can be sent until this is set',
        'fromNameLabel' => 'From name',
        'hostLabel' => 'SMTP host',
        'portLabel' => 'SMTP port',
        'usernameLabel' => 'SMTP username',
        'passwordLabel' => 'SMTP password',
        'passwordPlaceholder' => [
            'set' => 'Password is set - leave blank to keep it',
            'unset' => 'SMTP password',
        ],
        'encryptionLabel' => 'Encryption',
        'encryptionOptions' => [
            'tls' => 'TLS (STARTTLS, usual on port 587)',
            'ssl' => 'SSL (implicit TLS, usual on port 465)',
            'none' => 'None',
        ],
        'explainer' => 'Leave the SMTP host blank to send via PHP\'s mail() instead (not recommended - see README\'s deliverability section). No email can be sent at all until a "from" address is set.',
        'save' => 'Save',
    ],

    'MapSettingsForm' => [
        'legend' => 'Map tiles',
        'notice' => 'Leave blank to use OpenStreetMap. To use a keyed provider, paste its URL template with a literal {apiKey} where the key goes, plus the key and attribution below.',
        'urlLabel' => 'Tile URL template',
        'keyLabel' => 'API key',
        'keyPlaceholder' => 'Your tile provider API key',
        'attributionLabel' => 'Attribution',
        'attributionPlaceholder' => '© OpenStreetMap contributors',
        'save' => 'Save',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Google Sign-In',
        'clientIdLabel' => 'Client ID',
        'clientIdPlaceholder' => 'Google OAuth client ID',
        'secretLabel' => 'Client secret',
        'secretPlaceholder' => [
            'set' => 'Client secret is set - leave blank to keep it',
            'unset' => 'Google OAuth client secret',
        ],
        // {url} is filled in with the actual callback URL - not a link (there
        // is nothing to click), so a token in the phrasing rather than a
        // before/link/after split.
        'explainer' => 'Both are required for "Continue with Google" to appear on sign-up and sign-in. In your Google Cloud OAuth client, set the authorized redirect URI to {url} - clear the Client ID to turn it off.',
        'save' => 'Save',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'Site key',
        'turnstileSiteKeyPlaceholder' => 'Cloudflare Turnstile site key',
        'turnstileSecretKeyLabel' => 'Secret key',
        'turnstileSecretKeyPlaceholder' => [
            'set' => 'Secret key is set - leave blank to keep it',
            'unset' => 'Cloudflare Turnstile secret key',
        ],
        'turnstileExplainer' => 'Both keys are required for the CAPTCHA to appear on sign-up and sign-in. Clear the site key to turn it off.',
        'recaptchaLegend' => 'Google reCAPTCHA (account-lock recovery)',
        'recaptchaSiteKeyLabel' => 'Site key',
        'recaptchaSiteKeyPlaceholder' => 'Google reCAPTCHA v2 site key',
        'recaptchaSecretKeyLabel' => 'Secret key',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => 'Secret key is set - leave blank to keep it',
            'unset' => 'Google reCAPTCHA v2 secret key',
        ],
        'recaptchaExplainer' => 'Both keys are required. When set, an account that hits its login-attempt limit can get back in by passing this challenge instead of waiting out the lockout; when unset, the lockout is a hard wait. Use reCAPTCHA v2 ("I\'m not a robot"). Clear the site key to turn it off.',
        'save' => 'Save',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'Used by AI features on the site (trending-topic summaries, etc). Leave the model blank to use the Free Models Router, which OpenRouter picks at random from whatever is currently free and can never incur cost.',
        'keyLabel' => 'API key',
        'keyPlaceholder' => [
            'set' => 'API key is set - leave blank to keep it',
            'unset' => 'OpenRouter API key',
        ],
        'clearKeyLabel' => 'Remove the stored API key (turns AI features off)',
        'modelLabel' => 'Model',
        'neverSpendLabel' => 'Never allow this to spend money (recommended)',
        'explainer' => 'With the guard on, every request caps its price at zero, so it fails outright rather than falling back to a paid model when no free provider is available. Remove the stored API key to turn AI features off entirely.',
        'save' => 'Save',
    ],

    // One entry per SiteInfoSettingsForm descendant, keyed by the concrete
    // class - the abstract base looks itself up by static::class rather than
    // having an entry of its own, since it never renders un-subclassed.
    'AboutSettingsForm' => [
        'legend' => 'About',
        'description' => 'Plain text - blank lines separate paragraphs. First paragraph will be used as site description.',
        'save' => 'Save',
    ],

    'TermsSettingsForm' => [
        'legend' => 'Terms of Service',
        'description' => 'Plain text - blank lines separate paragraphs.',
        'save' => 'Save',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'Privacy Policy',
        'description' => 'Plain text - blank lines separate paragraphs.',
        'save' => 'Save',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'Email digest',
        'fieldLabel' => 'Closing paragraph',
        'notice' => 'Added near the end of every digest, after the list of what the member missed. Plain text. Leave it blank to go back to the wording this software ships with.',
        'save' => 'Save',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'Favicon',
        'currentAlt' => 'Current favicon',
        'save' => 'Upload Favicon',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'Front Page Image',
        'explainer' => 'Shown by other sites when someone shares a link to this one - Open Graph metadata only, never on the page. Cropped to 1200×630. Without one, link previews carry no image at all.',
        'currentAlt' => 'Current front page image',
        'save' => 'Upload Image',
    ],
];
