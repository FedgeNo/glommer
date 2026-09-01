<?php

declare(strict_types=1);

class MessageComposer extends FormForm
{
    public int $recipientId;
    public MessagePrivacyButton $privacyButton;

    /**
     * Whether the other person is a member here. Seeded by whoever builds the
     * composer, which has already loaded them - reading it here would put a
     * query in a render method.
     */
    public bool $recipientIsLocal = true;

    public function __construct(int $recipient_id, MessagePrivacyButton $privacy_button, bool $recipient_is_local = true)
    {
        parent::__construct();

        $this -> recipientId = $recipient_id;
        $this -> privacyButton = $privacy_button;
        $this -> recipientIsLocal = $recipient_is_local;
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        // Video calling is offered only in a thread with another member here.
        // It needs both people present in the same thread at once, which this
        // server can only know about its own, and a direct browser-to-browser
        // path there is no way to negotiate with someone elsewhere. Omitting
        // the attribute is what turns it off - Controllers.js's VideoCall keys on it.
        //
        // Carried here rather than on the list because the composer is the one
        // element a thread always has: a thread nobody has written in yet
        // renders no list at all, and a call is exactly what you might want
        // there.
        if ($this -> recipientIsLocal) {
            $this -> attributes['data-other-user-id'] = (string) $this -> recipientId;
            // The ICE configuration a call negotiates with. A fixed list rather
            // than a lookup, and carried on the thread rather than in the config
            // cookie, which every later request would send it back up in.
            $this -> attributes['data-ice-servers'] = (string) json_encode(VideoCall::iceServers());
        }

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
        $row -> addContent(new TextareaField('body', (string) ($words['bodyLabel'] ?? ''), (string) ($words['bodyPlaceholder'] ?? ''), 21845));
        $row -> addContent(new EmojiPicker());

        $row -> addContent(new SubmitButton((string) ($words['send'] ?? '')));

        $this -> contents[] = $row;

        return parent::toDOM();
    }
}
