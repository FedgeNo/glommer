<?php

declare(strict_types=1);

/**
 * The Admin Settings form for the paragraph this server adds to every
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

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        $textarea = new TextareaField(EmailDigest::PARAGRAPH_SETTING, (string) ($words['fieldLabel'] ?? ''), null, 1000);
        $textarea -> value = EmailDigest::paragraph();
        $fields -> addContent($textarea);

        $this -> contents[] = $fields;

        $this -> contents[] = new Notice((string) ($words['notice'] ?? ''));

        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
