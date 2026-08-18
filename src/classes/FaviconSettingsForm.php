<?php

declare(strict_types=1);

/**
 * The Admin Settings form for replacing the site favicon. The upload is
 * center-cropped, resized, and re-encoded to PNG by Favicon (never the
 * original bytes), and shows up on every page's <link rel="icon"> immediately.
 */
class FaviconSettingsForm extends FormForm
{

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        $current = new Image();
        $current -> src = Favicon::URL();
        $current -> alt = (string) ($words['currentAlt'] ?? '');
        $current -> class = 'FaviconPreview';
        $fields -> addContent($current);

        $file_input = new FileInput();
        $file_input -> name = 'favicon';
        $file_input -> attributes['accept'] = 'image/*';
        $fields -> addContent($file_input);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
