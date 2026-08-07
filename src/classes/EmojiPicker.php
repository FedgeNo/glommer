<?php

declare(strict_types=1);

/**
 * A trigger button and the <emoji-picker> it opens. Insertion is handled in
 * main.js by delegated click/emoji-click listeners, which locate the trigger's
 * containing <form> and insert into whichever text input it finds there (the
 * Quill .QuillEditor or a textarea) - this class just renders the UI.
 *
 * The picker itself is the popup, with nothing wrapped around it: the element
 * knows its own size and a box drawn around it can only be told the wrong one.
 * It is added by scripts/emoji-picker-init.js, which is also what defines the
 * element - there is nothing to render here until that has loaded.
 */
class EmojiPicker extends Div
{
    public ?string $class = 'EmojiPicker';

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new EmojiPickerTriggerButton();

        return parent::toDOM();
    }
}
