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

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        $explainer = new Paragraph((string) ($words['explainer'] ?? ''));
        $fields -> addContent($explainer);

        $current_url = FrontPageImage::URL();

        if ($current_url !== null) {
            $current = new Image();
            $current -> src = $current_url;
            $current -> alt = (string) ($words['currentAlt'] ?? '');
            $current -> class = 'FrontPageImagePreview';
            $fields -> addContent($current);
        }

        $file_input = new FileInput();
        $file_input -> name = 'frontPageImage';
        $file_input -> attributes['accept'] = 'image/*';
        $fields -> addContent($file_input);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
