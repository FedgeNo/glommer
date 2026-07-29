<?php

declare(strict_types=1);

/**
 * What /nearby shows before it knows where "near" is: an explanation and a
 * button that asks the browser for a location, then reloads the page with it in
 * the query string. The coordinates travel in the URL rather than being stored,
 * so a nearby feed is shareable and bookmarkable - and browsing another city's
 * local feed is just a different URL.
 */
class NearbyLocationPrompt extends Div
{
    public ?string $class = 'NearbyLocationPrompt';
    public array $mixins = ['Card', 'd-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $heading = new Heading2;
        $heading -> addContent('Posts near you');
        $this -> addContent($heading);

        $this -> addContent(new Paragraph('This shows the posts closest to a point - wherever there is activity, however far away it happens to be. Share your location to start from where you are, or pick a spot on the map instead.'));

        $actions = new Div;
        $actions -> mixins = ['d-flex', 'gap-2', 'align-items-center'];

        $button = new Button;
        $button -> class = 'NearbyLocateButton';
        $button -> mixins = ['Button'];
        $button -> addContent('Use my location');
        $actions -> addContent($button);

        $actions -> addContent(new Anchor(ServerURL::absolute('/map'), 'Pick on the map'));

        $this -> addContent($actions);

        return parent::toDOM();
    }
}
