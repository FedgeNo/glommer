<?php

declare(strict_types=1);

/**
 * The admin form for the image that stands for the site in link previews of
 * the front page. Purely metadata - it renders nowhere on the site itself -
 * and until one is uploaded the front page simply advertises no image, so a
 * crawler can't elect a random feed picture to represent the place.
 */
class FrontPageImageSettingsForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $fields = new Fieldset('Front Page Image');

        $explainer = new Paragraph(
            'Shown by other sites when someone shares a link to this one - Open Graph metadata only, never on the page. '
            . 'Cropped to 1200×630. Without one, link previews carry no image at all.'
        );
        $explainer -> mixins = ['muted', 'text-sm'];
        $fields -> addContent($explainer);

        $current_url = FrontPageImage::URL();

        if ($current_url !== null) {
            $current = new Image();
            $current -> src = $current_url;
            $current -> alt = 'Current front page image';
            $current -> class = 'FrontPageImagePreview';
            $fields -> addContent($current);
        }

        $file_input = new FileInput();
        $file_input -> name = 'frontPageImage';
        $file_input -> attributes['accept'] = 'image/*';
        $fields -> addContent($file_input);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Upload Image');

        return parent::toDOM();
    }
}
