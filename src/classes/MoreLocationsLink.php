<?php

declare(strict_types=1);

/**
 * The way back up from one place's page to the locations directory - the
 * same quiet line a reply page opens with to name its parent.
 */
class MoreLocationsLink extends Paragraph
{
    public ?string $class = 'MoreLocationsLink';
    public array $mixins = ['muted', 'text-sm'];

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = 'See ';
        $this -> addContent(new Anchor(ServerURL::absolute('/locations/'), 'more locations'));
        $this -> contents[] = '';

        return parent::toDOM();
    }
}
