<?php

declare(strict_types=1);

/**
 * The relays this server subscribes to, each with the control to withdraw.
 */
class RelayList extends ItemList
{
    public ?string $class = 'RelayList';
    public array $mixins = ['d-flex', 'flex-column'];

    protected function rows(): array
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');

        return DB::rows('
SELECT `actorURI`, `status`, `createdAt`
    FROM `Relays`
    ORDER BY `relayId` DESC
    LIMIT ? OFFSET ?
', 'RelayCard', 'ii', static::PAGE_SIZE + 1, $this -> offset);
    }
}
