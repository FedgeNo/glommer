<?php

declare(strict_types=1);

/**
 * The Admin Settings form for "Continue with Google" - the OAuth client id
 * and secret. The client id is shown (it's public - it ships in the redirect
 * URL anyway); the client secret is write-only, never rendered back, and a blank
 * submit leaves the stored secret unchanged - the same treatment as the
 * Turnstile keys.
 */
class GoogleAuthSettingsForm extends FormForm
{

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        $client_id = new InputField('googleAuthClientId', (string) ($words['clientIdLabel'] ?? ''), 'text', (string) ($words['clientIdPlaceholder'] ?? ''), 255);
        $client_id -> value = GoogleAuth::clientId();
        $client_id -> autocomplete = 'off';
        $client_id -> labelVisible = true;
        $fields -> addContent($client_id);

        $secret_is_set = (string) Settings::get(GoogleAuth::CLIENT_SECRET_SETTING, '') !== '';
        $secret_placeholder = (string) ($secret_is_set
            ? ($words['secretPlaceholder']['set'] ?? '')
            : ($words['secretPlaceholder']['unset'] ?? ''));
        $secret = new InputField('googleAuthSecret', (string) ($words['secretLabel'] ?? ''), 'text', $secret_placeholder, 255);
        $secret -> autocomplete = 'off';
        $secret -> labelVisible = true;
        $fields -> addContent($secret);

        $this -> contents[] = $fields;

        // Not a link - it's a value to paste into Google's console, not
        // something to click - so a token in the phrasing rather than the
        // before/link/after split a control gets.
        $explainer = str_replace('{url}', ServerURL::absolute('/auth-google-callback'), (string) ($words['explainer'] ?? ''));
        $this -> contents[] = new Paragraph($explainer);

        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
