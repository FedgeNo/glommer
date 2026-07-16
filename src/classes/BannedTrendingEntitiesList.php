<?php

declare(strict_types=1);

/**
 * The moderation list of every standing trending-entity ban, each with an
 * Unban control. Not paginated: standing entity bans are a small, curated set
 * (a moderator action per row), unlike the banned-users list.
 */
class BannedTrendingEntitiesList extends Div
{
    public ?string $class = 'd-flex flex-column gap-2 BannedTrendingEntitiesList';

    public function toDOM(): \DOMElement
    {
        $rows = Trending::bannedEntities();

        if ($rows === []) {
            $this -> addContent(new Notice('No banned trending entities.'));

            return parent::toDOM();
        }

        foreach ($rows as $row) {
            $this -> addContent(new BannedTrendingEntity($row));
        }

        return parent::toDOM();
    }
}
