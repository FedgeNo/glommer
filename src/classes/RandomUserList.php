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
class RandomUserList extends UserList
{

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();

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
', 'OtherUser', 'iiiiii', $viewer_id, $not_banned, $viewer_id, $viewer_id, static::PAGE_SIZE + 1, $this -> offset);
    }
}
