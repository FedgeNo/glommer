<?php

declare(strict_types=1);

/**
 * A trigger button and the <emoji-picker> panel it opens. Insertion is handled
 * in main.js by delegated click/emoji-click listeners, which locate the
 * trigger's containing <form> and insert into whichever text input it finds
 * there (the Quill .QuillEditor or a textarea) - this class just renders the UI.
 */
class EmojiPicker extends Div
{
    public ?string $class = 'EmojiPicker';

    public function toDOM(): \DOMElement
    {
        $trigger = new Button();
        $trigger -> type = 'button';
        $trigger -> class = 'EmojiPickerTriggerButton';
        $trigger -> mixins = ['Button'];
        $trigger -> attributes['aria-label'] = 'Insert emoji';
        $trigger -> contents[] = '🙂';
        $this -> contents[] = $trigger;

        $panel = new Div();
        $panel -> class = 'EmojiPickerPanel';
        $this -> contents[] = $panel;

        return parent::toDOM();
    }
}
