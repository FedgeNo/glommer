<?php

declare(strict_types=1);

class MessageComposer extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 MessageComposer';
    public int $recipientId;

    public function __construct(int $recipient_id)
    {
        parent::__construct();

        $this -> recipientId = $recipient_id;
    }

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/api/send-message');
        $this -> method = 'POST';

        $recipient_hidden = new HiddenInput();
        $recipient_hidden -> name = 'recipientId';
        $recipient_hidden -> value = (string) $this -> recipientId;
        $this -> contents[] = $recipient_hidden;

        $row = new Div();
        $row -> class = 'MessageComposerFields d-flex align-items-end gap-2';

        $row -> addContents(new TextareaField('body', 'Message', 'Write a message', 65535));
        $row -> addContents(new EmojiPickerButton());

        $row -> addContents(new SubmitButton('Send'));

        $this -> contents[] = $row;

        return parent::toDOM();
    }
}
