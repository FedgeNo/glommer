<?php

declare(strict_types=1);

class DeleteAccountForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 DeleteAccountForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/api/delete-account');
        $this -> method = 'POST';

        $fields = new Fieldset('Delete your account');
        $fields -> addContent(new Paragraph('This permanently deletes your account, posts, and messages. This can\'t be undone.'));
        $fields -> addContent(new InputField('currentPassword', 'Current password', 'password', 'Current password'));
        $this -> contents[] = $fields;

        $submit = new SubmitButton('Delete Account');
        $submit -> class .= ' DeleteAccountButton';
        $this -> contents[] = $submit;

        return parent::toDOM();
    }
}
