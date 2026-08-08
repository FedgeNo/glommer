<?php

declare(strict_types=1);

/**
 * The moderation log the admin reads.
 *
 * Every moderator action has been recorded since the table existed and
 * nothing ever read it back, so what matters here is that the reading holds
 * up where the record outlives the people in it: a moderator can be deleted
 * after acting, and so can whoever they acted on, and the entry has to
 * survive both. An audit log that disappears when somebody leaves is not one.
 */
class ModerationLogTest extends DatabaseTestCase
{
    private function clearLog(): void
    {
        DB::run('
DELETE
    FROM `ModerationActions`
');
    }

    /** Written the way the endpoints write it, as the given moderator. */
    private function log(int $moderator_id, string $action, ?int $target_user_id = null, ?string $type = null, ?int $target_id = null): void
    {
        DB::run('
INSERT INTO `ModerationActions` (`moderatorId`, `action`, `targetUserId`, `type`, `targetId`)
    VALUES (?, ?, ?, ?, ?)
', 'isisi', $moderator_id, $action, $target_user_id, $type, $target_id);
    }

    private function markup(ModerationActionCard $card): string
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $card -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return (string) HTMLObject::currentDocument() -> saveHTML($element);
    }

    public function testTheNewestActionIsListedFirst(): void
    {
        $this -> clearLog();

        $moderator = self::createUser();
        $this -> log($moderator, 'ban', self::createUser());
        $this -> log($moderator, 'unban', self::createUser());

        $items = new ModerationActionList() -> items;

        $this -> assertSame('unban', $items[0] -> action);
        $this -> assertSame('ban', $items[1] -> action);

        $this -> clearLog();
    }

    public function testAnActionNamesWhoDidItAndWhoItWasDoneTo(): void
    {
        $this -> clearLog();

        $moderator = User::load(self::createUser());
        $target = User::load(self::createUser());

        $this -> log((int) $moderator -> userId, 'ban', (int) $target -> userId);

        $markup = $this -> markup(new ModerationActionList() -> items[0]);

        $this -> assertTrue(str_contains($markup, (string) $moderator -> slug));
        $this -> assertTrue(str_contains($markup, (string) $target -> slug));
        $this -> assertTrue(str_contains($markup, 'banned'), 'the action reads as words');

        $this -> clearLog();
    }

    /**
     * The entry outlives the people in it, which is rather the point of
     * keeping one - so the join that names them cannot be the thing that
     * decides whether the entry is listed at all.
     */
    public function testAnActionSurvivesTheDeletionOfEverybodyInIt(): void
    {
        $this -> clearLog();

        $moderator = self::createUser();
        $target = self::createUser();
        $this -> log($moderator, 'ban', $target);

        User::delete($moderator);
        User::delete($target);

        $items = new ModerationActionList() -> items;

        $this -> assertCount(1, $items);

        $markup = $this -> markup($items[0]);

        $this -> assertTrue(str_contains($markup, 'since deleted'));

        $this -> clearLog();
    }

    /** An action against content names the content rather than a person. */
    public function testAnActionOnContentNamesTheContent(): void
    {
        $this -> clearLog();

        $this -> log(self::createUser(), 'deleteReportedContent', null, 'post', 4242);

        $markup = $this -> markup(new ModerationActionList() -> items[0]);

        $this -> assertTrue(str_contains($markup, 'post #4242'));

        $this -> clearLog();
    }

    /**
     * Every action the app records has words for it. A new one added to
     * ModerationAction::log without a description here would print its raw
     * camelCase name at the admin.
     */
    public function testEveryRecordedActionHasWordsForIt(): void
    {
        // Every string ModerationAction::log is called with anywhere in the
        // app. Add one there and this fails until it reads as English.
        $recorded = [
            'ban', 'unban', 'setMod', 'unsetMod', 'dismissReport',
            'deleteReportedContent', 'classifyReportedContent',
            'banTrendingEntity', 'unbanTrendingEntity',
        ];

        foreach ($recorded as $action) {
            $this -> assertTrue(
                ModerationActionCard::describes($action),
                $action . ' would be shown to the admin as its own camelCase name'
            );
        }
    }
}
