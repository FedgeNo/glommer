<?php

declare(strict_types=1);

/**
 * The admin Site Settings form for "Continue with Google" - the OAuth client id
 * and secret. The client id is shown (it's public - it ships in the redirect
 * URL anyway); the client secret is write-only, never rendered back, and a blank
 * submit leaves the stored secret unchanged - the same treatment as the
 * Turnstile keys.
 */
class GoogleAuthSettingsForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 GoogleAuthSettingsForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/admin/settings');
        $this -> method = 'POST';

        $fields = new Fieldset('Google Sign-In');

        $client_id = new InputField('googleAuthClientId', 'Client ID', 'text', 'Google OAuth client ID', 255);
        $client_id -> value = GoogleAuth::clientId();
        $client_id -> autocomplete = 'off';
        $client_id -> labelVisible = true;
        $fields -> addContent($client_id);

        $secret_is_set = (string) Settings::get(GoogleAuth::CLIENT_SECRET_SETTING, '') !== '';
        $secret_placeholder = $secret_is_set
            ? 'Client secret is set - leave blank to keep it'
            : 'Google OAuth client secret';
        $secret = new InputField('googleAuthSecret', 'Client secret', 'text', $secret_placeholder, 255);
        $secret -> autocomplete = 'off';
        $secret -> labelVisible = true;
        $fields -> addContent($secret);

        $this -> contents[] = $fields;

        $this -> contents[] = new Paragraph('Both are required for "Continue with Google" to appear on sign-up and sign-in. In your Google Cloud OAuth client, set the authorized redirect URI to ' . ServerURL::absolute('/auth-google-callback') . ' - clear the Client ID to turn it off.');

        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
