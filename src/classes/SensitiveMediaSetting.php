<?php

declare(strict_types=1);

/**
 * The reader's standing answer to media a post has classified as sensitive:
 * keep covering it, or show it like anything else.
 *
 * Off unless it is turned on. The cover exists so that nobody meets that media
 * without having decided to, and a default of "show me everything" would be
 * this site deciding on their behalf.
 *
 * Saved as it is toggled rather than behind a save button - there is one answer
 * here and nothing to review before committing it.
 */
class SensitiveMediaSetting extends Div
{
    public ?string $class = 'SensitiveMediaSetting';

    public function toDOM(): \DOMElement
    {
        $checkbox = new CheckboxInput();
        $checkbox -> name = 'showSensitiveMedia';
        $checkbox -> value = '1';

        if (SensitiveMedia::shownByDefault()) {
            $checkbox -> attributes['checked'] = 'checked';
        }

        $toggle = new SensitiveMediaSettingToggle();
        $toggle -> addContent($checkbox);
        $toggle -> addContent('Show sensitive media by default');

        $this -> addContent($toggle);

        return parent::toDOM();
    }
}
