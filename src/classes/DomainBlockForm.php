<?php

declare(strict_types=1);

/**
 * The moderation control for shutting out a whole server.
 *
 * The reason field is not decoration: a block outlives the incident and the
 * moderator, and a year later the only way to judge whether it still belongs is
 * whatever was written down when it was made.
 */
class DomainBlockForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $fields = new Fieldset('Block a server');

        $fields -> addContent(new Paragraph('Refuses everything from that server and everything under it: no deliveries in, none out, and existing follows in both directions are dropped.'));

        $domain = new InputField('domain', 'Server', 'text', 'example.social', 255);
        $domain -> labelVisible = true;
        $fields -> addContent($domain);

        $reason = new InputField('reason', 'Reason', 'text', 'Why this server is blocked', 255);
        $reason -> labelVisible = true;
        $fields -> addContent($reason);

        $this -> contents[] = $fields;
        $this -> contents[] = new SubmitButton('Block Server');

        return parent::toDOM();
    }
}
