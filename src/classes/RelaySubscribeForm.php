<?php

declare(strict_types=1);

/**
 * The control for joining a relay.
 *
 * What it costs is said before the address field rather than after it, because
 * the cost is the part an admin cannot work out for themselves: a relay's
 * volume is whatever the servers on the other side happen to publish, which
 * can be quiet one week and thousands of posts an hour the next.
 */
class RelaySubscribeForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $fields = new Fieldset('Subscribe to a relay');

        $fields -> addContent(new Paragraph(
            'A relay is a shared firehose: every public post from every other subscribed server arrives here, and this server\'s go out to all of them. It is how a new instance finds anyone at all, since federation otherwise only carries what somebody here already follows.'
        ));

        $fields -> addContent(new Paragraph(
            'The load is not yours to predict - it is whatever those servers publish, quiet one week and thousands of posts an hour the next, and your storage, delivery queue and moderation queue all carry it. Relayed posts stay out of the main and friends feeds; they go to the Relay Feed, which people open deliberately.'
        ));

        $actor = new InputField('actorURI', 'Relay address', 'text', 'https://relay.example/actor', 255);
        $fields -> addContent($actor);

        $fields -> addContent(new RelayFollowObjectField());

        $this -> contents[] = $fields;
        $this -> contents[] = new SubmitButton('Subscribe');

        return parent::toDOM();
    }
}
