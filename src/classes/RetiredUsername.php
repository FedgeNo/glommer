<?php

declare(strict_types=1);

/**
 * Usernames that have been used and given up.
 *
 * A username here is permanent - it cannot be changed, which is what display
 * names are for - so handing a released one to somebody else would make the new
 * person indistinguishable from the old one to anyone holding a link, a mention
 * or a bookmark.
 *
 * Federation raises the stakes past confusion. A member's actor URI is built
 * from their username, so a recycled name inherits the dead account's URI, and
 * every remote server still holding a follow would begin delivering a stranger's
 * posts into it. Nothing on the far side would notice: to them the account never
 * went away.
 *
 * Kept apart from Users because the account really is deleted. A row retained
 * just to reserve a string would hold open a foreign key for every post, message
 * and follow that was meant to go with it.
 */
class RetiredUsername
{
    public ?string $slug = null;
    public ?string $retiredAt = null;

    /** Retires a name at the moment its account goes. */
    public static function retire(string $slug): void
    {
        if ($slug === '') {
            return;
        }

        // INSERT IGNORE rather than an error: a name can only be retired once,
        // and a second attempt means the first already did the job.
        DB::run('
INSERT IGNORE INTO `RetiredUsernames` (`slug`)
    VALUES (?)
', 's', $slug);
    }

    /** Whether a name belonged to somebody who has since deleted their account. */
    public static function isRetired(string $slug): bool
    {
        return DB::row('
SELECT `slug`
    FROM `RetiredUsernames`
    WHERE `slug` = ?
', 'RetiredUsernameData', 's', $slug) !== null;
    }
}
