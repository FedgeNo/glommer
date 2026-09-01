<?php

declare(strict_types=1);

/**
 * The second login step for a 2FA-enabled account: enter the code emailed by
 * TwoFactor::sendCode(), or one of the single-use recovery codes - the same
 * field takes either. Shown by login.php when a pending-2FA session is
 * present (set by api/login.php after the password checks out), so a refresh
 * mid-2FA keeps the user on this step rather than dropping them back to the
 * password form. When the code email couldn't be sent (2FA fails closed
 * rather than skipping the step), the explanation asks for a recovery code
 * outright. Submits to api/verify-2fa (handled in Controllers.js).
 */
class TwoFactorForm extends FormForm
{

    public bool $emailFailed;

    public function __construct(bool $email_failed = false)
    {
        parent::__construct();

        $this -> emailFailed = $email_failed;
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $code_words = (string) ($words['code'] ?? '');
        $explanation_key = $this -> emailFailed ? 'explanationEmailFailed' : 'explanation';

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new Paragraph((string) ($words[$explanation_key] ?? '')));

        // Long enough for a formatted recovery code, not just the 6-digit
        // emailed one.
        $code = new InputField('code', $code_words, 'text', $code_words, 24);
        $code -> labelVisible = true;
        $code -> autocomplete = 'one-time-code';
        $fields -> addContent($code);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
