<?php

declare(strict_types=1);

/**
 * The "Trending" section on /topics/ - everything trending, of every kind.
 *
 * Separate from the /tags/ tag clouds (HashtagGraphSection /
 * TrendingHashtagSection / HashtagChip, which genuinely ARE hashtag-only and
 * unrelated to the trending engine) rather than sharing a component with them.
 */
class TrendingEntitySection extends ListSection
{
    public ?string $class = 'TrendingEntitySection';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    protected function list(): ItemLoader
    {
        return new TrendingEntityList();
    }

    public function toDOM(): \DOMElement
    {
        // Only when this class is the one rendering: PopularEntitySection is a
        // subclass and titles itself with the kind it is listing.
        if ($this -> heading === '') {
            $this -> heading = (string) (Strings::for(self::class)['heading'] ?? '');
        }

        return parent::toDOM();
    }
}
