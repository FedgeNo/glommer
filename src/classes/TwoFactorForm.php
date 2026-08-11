<?php

declare(strict_types=1);

/**
 * The second login step for a 2FA-enabled account: enter the code emailed by
 * TwoFactor::sendCode(). Shown by login.php when a pending-2FA session is
 * present (set by api/login.php after the password checks out), so a refresh
 * mid-2FA keeps the user on this step rather than dropping them back to the
 * password form. Submits to api/verify-2fa (handled in main.js).
 */
class TwoFactorForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $code_words = (string) ($words['code'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new Paragraph((string) ($words['explanation'] ?? '')));

        $code = new InputField('code', $code_words, 'text', $code_words, 6);
        $code -> labelVisible = true;
        $code -> autocomplete = 'one-time-code';
        $fields -> addContent($code);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
