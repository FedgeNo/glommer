<?php

declare(strict_types=1);

/**
 * Settings control for opt-in email 2FA. Shows the current state and offers
 * the opposite action - both enabling and disabling require the current
 * password (a security-sensitive change, same guard change-password uses),
 * so someone at an already-open session can't silently flip it. Submits to
 * api/two-factor (handled in main.js).
 */
class TwoFactorSettingsForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 TwoFactorSettingsForm';

    public bool $enabled;

    public function __construct(bool $enabled)
    {
        parent::__construct();

        $this -> enabled = $enabled;
    }

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/api/two-factor');
        $this -> method = 'POST';
        $this -> attributes['data-enabled'] = $this -> enabled ? '1' : '0';

        $legend = $this -> enabled ? 'Two-factor authentication is on' : 'Two-factor authentication is off';
        $fields = new Fieldset($legend);

        $explanation = $this -> enabled
            ? 'When you log in, we\'ll email a verification code you have to enter to finish signing in.'
            : 'Add a second step at login: we\'ll email a verification code you have to enter, so your password alone isn\'t enough to get in.';
        $fields -> addContent(new Paragraph($explanation));

        $current_password = new InputField('currentPassword', 'Current password', 'password', 'Current password');
        $current_password -> labelVisible = true;
        $fields -> addContent($current_password);

        $this -> contents[] = $fields;

        // The button's action is fixed by the current state - the endpoint
        // reads it from data-action so the two can't disagree.
        $button = new SubmitButton($this -> enabled ? 'Turn off two-factor authentication' : 'Turn on two-factor authentication');
        $button -> attributes['data-action'] = $this -> enabled ? 'disable' : 'enable';
        $this -> contents[] = $button;

        return parent::toDOM();
    }
}
