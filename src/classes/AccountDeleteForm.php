<?php

declare(strict_types=1);

class AccountDeleteForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {

        $fields = new Fieldset('Delete your account');
        $fields -> addContent(new Paragraph('This permanently deletes your account, posts, and messages. This can\'t be undone.'));
        $fields -> addContent(new InputField('currentPassword', 'Current password', 'password', 'Current password'));
        $this -> contents[] = $fields;

        $submit = new SubmitButton('Delete Account');
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

            $google_delete = new Button();
            $google_delete -> type = 'button';
            $google_delete -> class = 'GoogleAccountDeleteButton';
            $google_delete -> mixins = ['Button'];
            $google_delete -> contents[] = 'Verify with Google to delete';
            $this -> contents[] = $google_delete;
        }

        return parent::toDOM();
    }
}
