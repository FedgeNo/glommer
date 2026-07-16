<?php

declare(strict_types=1);

/**
 * The second login step for a 2FA-enabled account: enter the code emailed by
 * TwoFactor::sendCode(). Shown by login.php when a pending-2FA session is
 * present (set by api/login.php after the password checks out), so a refresh
 * mid-2FA keeps the user on this step rather than dropping them back to the
 * password form. Submits to api/verify-2fa (handled in main.js).
 */
class TwoFactorForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 TwoFactorForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/api/verify-2fa');
        $this -> method = 'POST';

        $fields = new Fieldset('Enter your verification code');
        $fields -> addContent(new Paragraph('We emailed you a verification code. Enter it below to finish logging in.'));

        $code = new InputField('code', 'Verification code', 'text', 'Verification code', 6);
        $code -> labelVisible = true;
        $code -> autocomplete = 'one-time-code';
        $fields -> addContent($code);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Verify');

        return parent::toDOM();
    }
}
