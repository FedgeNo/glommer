<?php

declare(strict_types=1);

/**
 * The Admin Settings bot-protection form: the Cloudflare Turnstile keys
 * (the everyday sign-up/sign-in CAPTCHA) and the Google reCAPTCHA keys (the
 * locked-account recovery challenge). For each, the site key is shown (it's
 * public - it ships in the widget anyway); the secret key is write-only - never
 * rendered back - so it can't leak into the page source, and a blank submit
 * leaves the stored secret unchanged.
 */
class BotProtectionSettingsForm extends FormForm
{

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['turnstileLegend'] ?? ''));

        // autocomplete='off' (and a plain text field for the secret, not a
        // password field) keeps the browser's password manager from autofilling
        // saved login credentials over these API-key fields. The secret is a
        // paste-a-value key, write-only and admin-only, so it isn't masked.
        $site_key = new InputField('turnstileSiteKey', (string) ($words['turnstileSiteKeyLabel'] ?? ''), 'text', (string) ($words['turnstileSiteKeyPlaceholder'] ?? ''), 255);
        $site_key -> value = Turnstile::siteKey();
        $site_key -> autocomplete = 'off';
        $site_key -> labelVisible = true;
        $fields -> addContent($site_key);

        $secret_is_set = (string) Settings::get(Turnstile::SECRET_KEY_SETTING, '') !== '';
        $secret_placeholder = (string) ($secret_is_set
            ? ($words['turnstileSecretKeyPlaceholder']['set'] ?? '')
            : ($words['turnstileSecretKeyPlaceholder']['unset'] ?? ''));
        $secret_key = new InputField('turnstileSecretKey', (string) ($words['turnstileSecretKeyLabel'] ?? ''), 'text', $secret_placeholder, 255);
        $secret_key -> autocomplete = 'off';
        $secret_key -> labelVisible = true;
        $fields -> addContent($secret_key);

        $this -> contents[] = $fields;

        $this -> contents[] = new Paragraph((string) ($words['turnstileExplainer'] ?? ''));

        $recaptcha_fields = new Fieldset((string) ($words['recaptchaLegend'] ?? ''));

        $recaptcha_site_key = new InputField('recaptchaSiteKey', (string) ($words['recaptchaSiteKeyLabel'] ?? ''), 'text', (string) ($words['recaptchaSiteKeyPlaceholder'] ?? ''), 255);
        $recaptcha_site_key -> value = ReCaptcha::siteKey();
        $recaptcha_site_key -> autocomplete = 'off';
        $recaptcha_site_key -> labelVisible = true;
        $recaptcha_fields -> addContent($recaptcha_site_key);

        $recaptcha_secret_is_set = (string) Settings::get(ReCaptcha::SECRET_KEY_SETTING, '') !== '';
        $recaptcha_secret_placeholder = (string) ($recaptcha_secret_is_set
            ? ($words['recaptchaSecretKeyPlaceholder']['set'] ?? '')
            : ($words['recaptchaSecretKeyPlaceholder']['unset'] ?? ''));
        $recaptcha_secret_key = new InputField('recaptchaSecretKey', (string) ($words['recaptchaSecretKeyLabel'] ?? ''), 'text', $recaptcha_secret_placeholder, 255);
        $recaptcha_secret_key -> autocomplete = 'off';
        $recaptcha_secret_key -> labelVisible = true;
        $recaptcha_fields -> addContent($recaptcha_secret_key);

        $this -> contents[] = $recaptcha_fields;

        $this -> contents[] = new Paragraph((string) ($words['recaptchaExplainer'] ?? ''));

        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
