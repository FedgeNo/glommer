<?php

declare(strict_types=1);

/**
 * Who is reading which message thread right now. A person can only read one
 * thread at a time, so this is two columns on their own Users row rather than a
 * table of its own: who they have open, and when they were last known to still
 * be there.
 *
 * There is no reliable "closed the tab" event, so presence is a heartbeat the
 * open thread repeats and a window it has to land inside - someone whose last
 * beat is older than PRESENCE_SECONDS has gone, whether they navigated away,
 * slept the machine, or lost the network.
 */
class ChatPresence
{
    /**
     * How recently a heartbeat must have landed for someone to still count as
     * reading the thread. Comfortably more than the interval the client beats
     * at, so one missed request doesn't read as leaving.
     */
    public const PRESENCE_SECONDS = 30;

    /** Records that $user_id has $other_user_id's thread open, as of now. */
    public static function enter(int $user_id, int $other_user_id): void
    {
        DB::run('
UPDATE `Users`
    SET `chatOtherUserId` = ?, `chatLastSeen` = NOW()
    WHERE `userId` = ?
', 'ii', $other_user_id, $user_id);
    }

    /**
     * Clears it, for a reader who left the page. Only ever a courtesy - the
     * window above is what actually decides, since a closed laptop sends
     * nothing.
     */
    public static function leave(int $user_id): void
    {
        DB::run('
UPDATE `Users`
    SET `chatOtherUserId` = NULL, `chatLastSeen` = NULL
    WHERE `userId` = ?
', 'i', $user_id);
    }

    /**
     * Whether $other_user_id has $viewer_id's own thread open right now - both
     * halves matter, since someone reading a different thread is online but not
     * here, and only the pair being in the same thread at the same time makes a
     * call worth offering.
     */
    public static function isPresentWith(int $other_user_id, int $viewer_id): bool
    {
        $window_seconds = self::PRESENCE_SECONDS;

        return DB::row('
SELECT `userId`
    FROM `Users`
    WHERE `userId` = ? AND `chatOtherUserId` = ? AND `chatLastSeen` > NOW() - INTERVAL ? SECOND
', 'User', 'iii', $other_user_id, $viewer_id, $window_seconds) !== null;
    }
}
