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

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        $fields -> addContent(new Paragraph((string) ($words['explainerOne'] ?? '')));

        $fields -> addContent(new Paragraph((string) ($words['explainerTwo'] ?? '')));

        $actor = new InputField(
            'actorURI',
            (string) ($words['addressLabel'] ?? ''),
            'text',
            (string) ($words['addressPlaceholder'] ?? ''),
            255
        );
        $fields -> addContent($actor);

        $fields -> addContent(new RelayFollowObjectField());

        $this -> contents[] = $fields;
        $this -> contents[] = new SubmitButton((string) ($words['submitLabel'] ?? ''));

        return parent::toDOM();
    }
}
