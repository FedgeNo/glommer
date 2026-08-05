<?php

declare(strict_types=1);

/**
 * The relays this server subscribes to, each with the control to withdraw.
 */
class RelayList extends ItemList
{
    public ?string $class = 'RelayList';
    public array $mixins = ['d-flex', 'flex-column'];

    protected string $emptyNotice = 'Not subscribed to any relays. Nothing arrives here except what members follow.';

    protected function rows(): array
    {
        return DB::rows('
SELECT `actorURI`, `status`, `createdAt`
    FROM `Relays`
    ORDER BY `relayId` DESC
    LIMIT ? OFFSET ?
', 'RelayCard', 'ii', static::PAGE_SIZE + 1, $this -> offset);
    }
}
