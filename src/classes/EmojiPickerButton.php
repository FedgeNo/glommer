<?php

declare(strict_types=1);

/**
 * A trigger button that opens a <emoji-picker> panel. Insertion is handled
 * in main.js by delegated click/emoji-click listeners, which locate this
 * button's containing <form> and insert into whichever text input it finds
 * there (the Quill #editor or a textarea) - this class just renders the UI.
 */
class EmojiPickerButton extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'EmojiPickerButton';

    public function toDOM(): \DOMElement
    {
        $trigger = new Button();
        $trigger -> type = 'button';
        $trigger -> class = 'Btn EmojiTriggerButton';
        $trigger -> attributes['aria-label'] = 'Insert emoji';
        $trigger -> contents[] = '🙂';
        $this -> contents[] = $trigger;

        $panel = new Div();
        $panel -> class = 'EmojiPickerPanel';
        $this -> contents[] = $panel;

        return parent::toDOM();
    }
}
