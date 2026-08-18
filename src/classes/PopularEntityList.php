<?php

declare(strict_types=1);

/**
 * Every topic of one kind that is still kept, most talked about first - what
 * /topics/{type}/ lists.
 *
 * Popular, not trending: trending is what is spiking, which the front of
 * /topics/ already answers and which changes hour to hour. This is the standing
 * list of the kind, ordered by how much has been said about each - so a topic
 * that has not been mentioned in weeks still has its place in it.
 *
 * Its items are Entities, since that is the row: the difference
 * between this list and TrendingEntityList is which topics and in what order,
 * not what one looks like.
 */
class PopularEntityList extends TrendingEntityList
{
    public ?string $class = 'PopularEntityList';

    public ?string $type = null;

    protected function rows(): array
    {
        EntityRanker::refreshIfStale();

        return DB::rows('
SELECT *
    FROM `Entities`
    WHERE `type` = ?
    ORDER BY `popularity` DESC, `entityId` ASC
    LIMIT ? OFFSET ?
', Entity::class, 'sii', (string) $this -> type, static::PAGE_SIZE + 1, $this -> offset);
    }

    /** @param Entity[] $items @return Entity[] */
    protected function arrange(array $items): array
    {
        foreach ($items as $entity) {
            $entity -> countsAllTime = true;
        }

        return $items;
    }

    public function toJSON(): array
    {
        $page = parent::toJSON();
        $page['items'] = array_map(static fn (Entity $entity): array => $entity -> payload(), $page['items']);

        return $page;
    }

    /** @return array<string, string> */
    protected function dataAttributes(): array
    {
        return ['data-infinite-scroll' => (string) json_encode([
            'endpoint' => '/api/topic-history',
            'itemType' => 'Entity',
            // Everything else named here rides along in the request, which is
            // how the next page knows which of the twelve lists it continues.
            'entityType' => EntityType::slug((string) $this -> type),
        ])];
    }
}
