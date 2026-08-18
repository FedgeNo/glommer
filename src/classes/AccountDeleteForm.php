<?php

declare(strict_types=1);

class AccountDeleteForm extends FormForm
{

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $current_password_words = (string) ($words['currentPassword'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new Paragraph((string) ($words['warning'] ?? '')));
        $fields -> addContent(new InputField('currentPassword', $current_password_words, 'password', $current_password_words));
        $this -> contents[] = $fields;

        $submit = new SubmitButton((string) ($words['submit'] ?? ''));
        $submit -> class .= ' AccountDeleteButton';
        $this -> contents[] = $submit;

        // Anyone who signs in with Google - including an email/password account
        // that later switched to it - has no usable password to confirm with,
        // so offer deletion via a Google re-verification instead. Shown
        // whenever Google sign-in is configured; the callback only deletes if
        // the verified Google email matches this account's email. A plain
        // type=button so it never submits the password form it sits in.
        if (GoogleAuth::isEnabled()) {
            $this -> contents[] = new AuthDivider();

            $this -> contents[] = new GoogleAccountDeleteButton();
        }

        return parent::toDOM();
    }
}
