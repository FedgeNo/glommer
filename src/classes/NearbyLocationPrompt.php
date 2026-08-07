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
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $heading = new Heading2;
        $heading -> addContent('Posts near you');
        $this -> addContent($heading);

        $this -> addContent(new Paragraph('This shows the posts closest to a point - wherever there is activity, however far away it happens to be. Share your location to start from where you are, or pick a spot on the map instead.'));

        $actions = new Div;
        $actions -> mixins = ['d-flex', 'gap-2', 'align-items-center', 'flex-wrap'];

        $button = new Button;
        $button -> class = 'NearbyLocationButton';
        $button -> mixins = ['Button'];
        $button -> addContent('Use my location');
        $actions -> addContent($button);

        $map_link = new Anchor(ServerURL::absolute('/map'), 'Pick on the map');
        $map_link -> class = 'Button';
        $actions -> addContent($map_link);

        $this -> addContent($actions);

        // Or name the place instead: suggestions from the local gazetteer as
        // you type, and clicking one opens this same page on its coordinates.
        // Members only, because the endpoint behind it is a real search (see
        // api/search-places.php); signed-out visitors keep the two buttons.
        if (Auth::check() && Place::count() > 0) {
            $search = new Div;
            $search -> class = 'NearbyPlaceSearch';

            $input = new Input;
            $input -> class = 'NearbyPlaceSearchInput';
            $input -> attributes['type'] = 'search';
            $input -> attributes['placeholder'] = 'Or type a place name…';
            $input -> attributes['autocomplete'] = 'off';
            $input -> attributes['aria-label'] = 'Search for a place';
            $search -> addContent($input);

            $this -> addContent($search);
        }

        return parent::toDOM();
    }
}
