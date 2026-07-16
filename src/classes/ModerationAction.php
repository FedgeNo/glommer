<?php

declare(strict_types=1);

/**
 * The moderation audit log: one row per moderator action (ban, unban,
 * promote/demote mod, dismiss report, delete reported content), recording who
 * did what to whom/what and when. Write-only from the app's perspective for
 * now - it exists so every future "why was I banned?" or "which mod deleted
 * that?" has an answer in the database.
 */
class ModerationAction
{
    public static function log(string $action, ?int $target_user_id = null, ?string $target_type = null, ?int $target_id = null, ?int $report_id = null): void
    {
        $moderator_id = (int) Auth::id();

        DB::run('
INSERT INTO `ModerationActions` (`moderatorId`, `action`, `targetUserId`, `targetType`, `targetId`, `reportId`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'isisii', $moderator_id, $action, $target_user_id, $target_type, $target_id, $report_id);
    }
}
