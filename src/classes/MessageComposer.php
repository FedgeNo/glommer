<?php

declare(strict_types=1);

class MessageComposer extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];
    public int $recipientId;
    public MessagePrivacyButton $privacyButton;

    public function __construct(int $recipient_id, MessagePrivacyButton $privacy_button)
    {
        parent::__construct();

        $this -> recipientId = $recipient_id;
        $this -> privacyButton = $privacy_button;
    }

    public function toDOM(): \DOMElement
    {
        $recipient_hidden = new HiddenInput();
        $recipient_hidden -> name = 'recipientId';
        $recipient_hidden -> value = (string) $this -> recipientId;
        $this -> contents[] = $recipient_hidden;

        // Laid out as a grid (components.css): the textarea fills the left,
        // and the privacy chip sits to its right, directly above the emoji
        // picker and Send button.
        $row = new MessageComposerFields();
        $row -> addContent($this -> privacyButton);

        // The server enforces 65535 BYTES (the Messages.body column's real
        // capacity, checked via strlen() in api/send-message.php), but
        // maxlength counts UTF-16 code units, not bytes - a message could
        // pass a 65535 maxlength while exceeding 65535 bytes (a 3-byte UTF-8
        // BMP character, common in CJK text, is a single UTF-16 unit - the
        // worst case, worse than a 4-byte astral character at 2 units).
        // floor(65535 / 3) guarantees the byte cap is never exceeded
        // regardless of content, so a message the browser lets through never
        // gets rejected server-side as "too long".
        $row -> addContent(new TextareaField('body', 'Message', 'Write a message', 21845));
        $row -> addContent(new EmojiPicker());

        $row -> addContent(new SubmitButton('Send'));

        $this -> contents[] = $row;

        return parent::toDOM();
    }
}
