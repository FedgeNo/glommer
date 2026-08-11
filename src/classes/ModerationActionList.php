<?php

declare(strict_types=1);

/**
 * The moderation log, newest first - every ban, promotion, dismissal and
 * deletion a moderator has made.
 *
 * The admin's to read, not the moderators': it is the record of what they did,
 * and the person it answers to is the one who appointed them. That is why it
 * lives on Site Settings rather than on the Mod Settings page.
 *
 * Both names are joined here rather than looked up per row, so a page of the
 * log costs one query. They are left outer joins because an account can be
 * deleted after the thing it did was recorded - the entry outlives the person,
 * which is rather the point of keeping one.
 */
class ModerationActionList extends ItemList
{
    public ?string $class = 'ModerationActionList';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    protected function rows(): array
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');

        $rows = DB::rows('
SELECT `a`.*, `m`.`slug` AS `moderatorUsername`, `t`.`slug` AS `targetUsername`
    FROM `ModerationActions` `a`
    LEFT JOIN `Users` `m` ON `m`.`userId` = `a`.`moderatorId`
    LEFT JOIN `Users` `t` ON `t`.`userId` = `a`.`targetUserId`
    ORDER BY `a`.`actionId` DESC
    LIMIT ? OFFSET ?
', 'ModerationActionData', 'ii', static::PAGE_SIZE + 1, $this -> offset);

        return array_map(
            static fn (ModerationActionData $row): ModerationActionCard => ModerationActionCard::fromRow($row),
            $rows
        );
    }
}
