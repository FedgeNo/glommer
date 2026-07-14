<?php

declare(strict_types=1);

/**
 * Empty/hidden by default - populated by main.js once a link preview fetch
 * stages an image, so the user can see what would be attached and remove it
 * before posting. The seed input is what actually gets submitted; removing
 * the preview clears it and tells the server to discard the staged files.
 */
class LinkImagePreview extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'LinkImagePreview';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['style'] = 'display: none';

        $image = new Image();
        $image -> class = 'LinkImagePreviewThumb';
        $image -> alt = 'Link preview image';
        $this -> contents[] = $image;

        $remove_button = new Button();
        $remove_button -> type = 'button';
        $remove_button -> class = 'Btn RemoveLinkImageButton';
        $remove_button -> contents[] = 'Remove image';
        $this -> contents[] = $remove_button;

        $seed_input = new HiddenInput();
        $seed_input -> name = 'linkImageSeed';
        $this -> contents[] = $seed_input;

        return parent::toDOM();
    }
}
