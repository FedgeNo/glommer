<?php

declare(strict_types=1);

/**
 * The admin Site Settings form for the paragraph this server adds to every
 * email digest.
 *
 * The rest of a digest is the member's own missed activity and, where an API
 * key is configured, a written summary of it - all of it the same shape on
 * every installation. This is the one part an admin writes, so a server can
 * sign off in its own voice. Blank restores the shipped wording rather than
 * removing the paragraph, the same as the About and Terms texts.
 */
class EmailDigestSettingsForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $fields = new Fieldset('Email digest');

        $textarea = new TextareaField(EmailDigest::PARAGRAPH_SETTING, 'Closing paragraph', null, 1000);
        $textarea -> value = EmailDigest::paragraph();
        $fields -> addContent($textarea);

        $this -> contents[] = $fields;

        $this -> contents[] = new Notice('Added near the end of every digest, after the list of what the member missed. Plain text. Leave it blank to go back to the wording this software ships with.');

        $this -> contents[] = new SubmitButton('Save');

        return parent::toDOM();
    }
}
