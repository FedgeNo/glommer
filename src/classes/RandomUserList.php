<?php

declare(strict_types=1);

/**
 * Accounts picked at random - what a viewer with no friends yet (or no
 * friends-of-friends worth ranking) gets shown, so the list is never empty.
 *
 * Someone the viewer has blocked, in either direction, is left out: this list
 * is the site putting accounts in front of a person who didn't ask for them,
 * which is exactly what a block should stop. That's a different question from
 * search, where the viewer is deliberately looking for a specific account.
 */
class RandomUserList extends UserListSection
{
    protected string $heading = 'People to discover';

    /** Who the list is being built for - the blocks are relative to them. */
    public int $viewerId = 0;

    protected function rows(): array
    {
        $not_banned = 0;

        return DB::rows('
SELECT `u`.*
    FROM `Users` `u`
    WHERE `u`.`userId` != ? AND `u`.`banned` = ?
        AND NOT EXISTS (
            SELECT 1
                FROM `Blocks` `b`
                WHERE (`b`.`blockerId` = ? AND `b`.`blockedId` = `u`.`userId`) OR (`b`.`blockerId` = `u`.`userId` AND `b`.`blockedId` = ?)
        )
    ORDER BY RAND()
    LIMIT ? OFFSET ?
', 'OtherUser', 'iiiiii', $this -> viewerId, $not_banned, $this -> viewerId, $this -> viewerId, static::PAGE_SIZE + 1, $this -> offset);
    }
}
