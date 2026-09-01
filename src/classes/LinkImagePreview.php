<?php

declare(strict_types=1);

/**
 * Empty/hidden by default - populated by Controllers.js once a link preview fetch
 * stages an image, so the user can see what would be attached and remove it
 * before posting. The seed input is what actually gets submitted; removing
 * the preview clears it and tells the server to discard the staged files.
 */
class LinkImagePreview extends Div
{
    public ?string $class = 'LinkImagePreview';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['style'] = 'display: none';

        $image = new LinkImagePreviewThumb();
        $image -> alt = (string) (Strings::for(self::class)['alt'] ?? '');
        $this -> contents[] = $image;

        $this -> contents[] = new LinkImageRemoveButton();

        $seed_input = new HiddenInput();
        $seed_input -> name = 'linkImageSeed';
        $this -> contents[] = $seed_input;

        return parent::toDOM();
    }
}
