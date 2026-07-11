<?php

declare(strict_types=1);

/**
 * The admin Site Settings form. Currently just the Cloudflare Turnstile keys.
 * The site key is shown (it's public - it ships in the widget anyway); the
 * secret key is write-only - never rendered back - so it can't leak into the
 * page source, and a blank submit leaves the stored secret unchanged.
 */
class AdminSettingsForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 AdminSettingsForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/admin/settings');
        $this -> method = 'POST';

        $fields = new Fieldset('Cloudflare Turnstile');

        // autocomplete='off' (and a plain text field for the secret, not a
        // password field) keeps the browser's password manager from autofilling
        // saved login credentials over these API-key fields. The secret is a
        // paste-a-value key, write-only and admin-only, so it isn't masked.
        $site_key = new InputField('turnstileSiteKey', 'Site key', 'text', 'Cloudflare Turnstile site key', 255);
        $site_key -> value = Turnstile::siteKey();
        $site_key -> autocomplete = 'off';
        $fields -> addContents($site_key);

        $secret_is_set = (string) Settings::get(Turnstile::SECRET_KEY_SETTING, '') !== '';
        $secret_placeholder = $secret_is_set
            ? 'Secret key is set - leave blank to keep it'
            : 'Cloudflare Turnstile secret key';
        $secret_key = new InputField('turnstileSecretKey', 'Secret key', 'text', $secret_placeholder, 255);
        $secret_key -> autocomplete = 'off';
        $fields -> addContents($secret_key);

        $this -> contents[] = $fields;

        $this -> contents[] = new Paragraph('Both keys are required for the CAPTCHA to appear on sign-up and sign-in. Clear the site key to turn it off.');

        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
