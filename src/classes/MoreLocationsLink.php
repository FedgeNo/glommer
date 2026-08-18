<?php

declare(strict_types=1);

/**
 * The way back up from one place's page to the locations directory - the
 * same quiet line a reply page opens with to name its parent.
 */
class MoreLocationsLink extends Paragraph
{
    public ?string $class = 'MoreLocationsLink';

    public function toDOM(): \DOMElement
    {
        $sentence = Strings::for(self::class)['moreLocations'] ?? [];

        $this -> contents[] = (string) ($sentence['before'] ?? '');
        $this -> addContent(new Anchor(ServerURL::absolute('/locations/'), (string) ($sentence['link'] ?? '')));
        $this -> contents[] = (string) ($sentence['after'] ?? '');

        return parent::toDOM();
    }
}
