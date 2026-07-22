<?php

declare(strict_types=1);

/**
 * The admin Site Settings bot-protection form: the Cloudflare Turnstile keys
 * (the everyday sign-up/sign-in CAPTCHA) and the Google reCAPTCHA keys (the
 * locked-account recovery challenge). For each, the site key is shown (it's
 * public - it ships in the widget anyway); the secret key is write-only - never
 * rendered back - so it can't leak into the page source, and a blank submit
 * leaves the stored secret unchanged.
 */
class AdminSettingsForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 AdminSettingsForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/admin/settings');
        $this -> method = 'POST';

        $fields = new Fieldset('Cloudflare Turnstile');

        // autocomplete='off' (and a plain text field for the secret, not a
        // password field) keeps the browser's password manager from autofilling
        // saved login credentials over these API-key fields. The secret is a
        // paste-a-value key, write-only and admin-only, so it isn't masked.
        $site_key = new InputField('turnstileSiteKey', 'Site key', 'text', 'Cloudflare Turnstile site key', 255);
        $site_key -> value = Turnstile::siteKey();
        $site_key -> autocomplete = 'off';
        $site_key -> labelVisible = true;
        $fields -> addContent($site_key);

        $secret_is_set = (string) Settings::get(Turnstile::SECRET_KEY_SETTING, '') !== '';
        $secret_placeholder = $secret_is_set
            ? 'Secret key is set - leave blank to keep it'
            : 'Cloudflare Turnstile secret key';
        $secret_key = new InputField('turnstileSecretKey', 'Secret key', 'text', $secret_placeholder, 255);
        $secret_key -> autocomplete = 'off';
        $secret_key -> labelVisible = true;
        $fields -> addContent($secret_key);

        $this -> contents[] = $fields;

        $this -> contents[] = new Paragraph('Both keys are required for the CAPTCHA to appear on sign-up and sign-in. Clear the site key to turn it off.');

        $recaptcha_fields = new Fieldset('Google reCAPTCHA (account-lock recovery)');

        $recaptcha_site_key = new InputField('recaptchaSiteKey', 'Site key', 'text', 'Google reCAPTCHA v2 site key', 255);
        $recaptcha_site_key -> value = ReCaptcha::siteKey();
        $recaptcha_site_key -> autocomplete = 'off';
        $recaptcha_site_key -> labelVisible = true;
        $recaptcha_fields -> addContent($recaptcha_site_key);

        $recaptcha_secret_is_set = (string) Settings::get(ReCaptcha::SECRET_KEY_SETTING, '') !== '';
        $recaptcha_secret_placeholder = $recaptcha_secret_is_set
            ? 'Secret key is set - leave blank to keep it'
            : 'Google reCAPTCHA v2 secret key';
        $recaptcha_secret_key = new InputField('recaptchaSecretKey', 'Secret key', 'text', $recaptcha_secret_placeholder, 255);
        $recaptcha_secret_key -> autocomplete = 'off';
        $recaptcha_secret_key -> labelVisible = true;
        $recaptcha_fields -> addContent($recaptcha_secret_key);

        $this -> contents[] = $recaptcha_fields;

        $this -> contents[] = new Paragraph('Both keys are required. When set, an account that hits its login-attempt limit can get back in by passing this challenge instead of waiting out the lockout; when unset, the lockout is a hard wait. Use reCAPTCHA v2 ("I\'m not a robot"). Clear the site key to turn it off.');

        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
