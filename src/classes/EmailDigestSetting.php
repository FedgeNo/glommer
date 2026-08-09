<?php

declare(strict_types=1);

/**
 * Whether this member wants the mail that tells them what they missed while
 * they were away.
 *
 * On unless it is turned off, because the person it is for is by definition
 * not here to find a switch and turn it on. Every digest carries a one-click
 * link that turns it off without signing in - this is the same answer, given
 * from the inside.
 *
 * Saved as it is toggled rather than behind a save button - there is one
 * answer here and nothing to review before committing it.
 */
class EmailDigestSetting extends Div
{
    public ?string $class = 'EmailDigestSetting';

    public function toDOM(): \DOMElement
    {
        $checkbox = new CheckboxInput();
        $checkbox -> name = 'emailDigests';
        $checkbox -> value = '1';

        if ((int) (Auth::user() ?-> emailDigests ?? 1) === 1) {
            $checkbox -> attributes['checked'] = 'checked';
        }

        $toggle = new EmailDigestSettingToggle();
        $toggle -> addContent($checkbox);
        $toggle -> addContent('Email me what I missed when I have been away a while');

        $this -> addContent($toggle);

        return parent::toDOM();
    }
}
