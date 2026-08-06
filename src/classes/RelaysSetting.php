<?php

declare(strict_types=1);

/**
 * Subscribing to a relay, and the ones already subscribed to. Short enough to
 * read in one go, so it sits on the Admin Settings page rather than a page of
 * its own.
 */
class RelaysSetting extends Div
{
    public ?string $class = 'RelaysSetting';

    public function toDOM(): \DOMElement
    {
        $this -> addContent(new RelaySubscribeForm());
        $this -> addContent(new RelayList());

        return parent::toDOM();
    }
}
