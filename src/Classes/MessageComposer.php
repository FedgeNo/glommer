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
        $this -> action = ServerURL::absolute('/api/send-message');
        $this -> method = 'POST';

        $recipient_hidden = new HiddenInput();
        $recipient_hidden -> name = 'recipientId';
        $recipient_hidden -> value = (string) $this -> recipientId;
        $this -> contents[] = $recipient_hidden;

        $row = new Div();
        $row -> class = 'MessageComposerFields d-flex align-items-end gap-2';

        // The server enforces 65535 BYTES (the Messages.body column's real
        // capacity, checked via strlen() in api/send-message.php), but
        // maxlength counts UTF-16 code units, not bytes - a message could
        // pass a 65535 maxlength while exceeding 65535 bytes (a 3-byte UTF-8
        // BMP character, common in CJK text, is a single UTF-16 unit - the
        // worst case, worse than a 4-byte astral character at 2 units).
        // floor(65535 / 3) guarantees the byte cap is never exceeded
        // regardless of content, so a message the browser lets through never
        // gets rejected server-side as "too long".
        $row -> addContents(new TextareaField('body', 'Message', 'Write a message', 21845));
        $row -> addContents(new EmojiPickerButton());

        $row -> addContents(new SubmitButton('Send'));

        $this -> contents[] = $row;

        return parent::toDOM();
    }
}
