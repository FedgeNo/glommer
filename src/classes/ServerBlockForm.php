<?php

declare(strict_types=1);

/**
 * The moderation control for shutting out a whole server.
 *
 * The reason field is not decoration: a block outlives the incident and the
 * moderator, and a year later the only way to judge whether it still belongs is
 * whatever was written down when it was made.
 */
class ServerBlockForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        $fields -> addContent(new Paragraph((string) ($words['description'] ?? '')));

        $domain = new InputField('domain', (string) ($words['serverLabel'] ?? ''), 'text', (string) ($words['serverPlaceholder'] ?? ''), 255);
        $domain -> labelVisible = true;
        $fields -> addContent($domain);

        $reason = new InputField('reason', (string) ($words['reasonLabel'] ?? ''), 'text', (string) ($words['reasonPlaceholder'] ?? ''), 255);
        $reason -> labelVisible = true;
        $fields -> addContent($reason);

        $this -> contents[] = $fields;
        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
