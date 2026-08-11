<?php

declare(strict_types=1);

/**
 * Settings control for opt-in email 2FA. Shows the current state and offers
 * the opposite action - both enabling and disabling require the current
 * password (a security-sensitive change, same guard change-password uses),
 * so someone at an already-open session can't silently flip it. Submits to
 * api/two-factor (handled in main.js).
 */
class TwoFactorSettingsForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public bool $enabled;

    public function __construct(bool $enabled)
    {
        parent::__construct();

        $this -> enabled = $enabled;
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $state = $this -> enabled ? 'on' : 'off';
        $current_password_words = (string) ($words['currentPassword'] ?? '');

        $fields = new Fieldset((string) ($words['legend'][$state] ?? ''));
        $fields -> addContent(new Paragraph((string) ($words['explanation'][$state] ?? '')));

        $current_password = new InputField('currentPassword', $current_password_words, 'password', $current_password_words);
        $current_password -> labelVisible = true;
        $fields -> addContent($current_password);

        $this -> contents[] = $fields;

        // The button's action is fixed by the current state - the endpoint
        // reads it from data-action so the two can't disagree.
        $button = new SubmitButton((string) ($words['submit'][$state] ?? ''));
        $button -> attributes['data-action'] = $this -> enabled ? 'disable' : 'enable';
        $this -> contents[] = $button;

        return parent::toDOM();
    }
}
