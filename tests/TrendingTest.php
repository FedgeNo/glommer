<?php

declare(strict_types=1);

/**
 * What gets to be a trending entity.
 *
 * The window this reads is the whole of the feature: everything downstream
 * only scores and sorts what the window handed it. It had excluded posts from
 * other servers, which on a server carrying a relay is very nearly all of
 * them - the extractor saw a few dozen posts, nothing ever reached the
 * distinct-author bar, and the trending list stayed empty however much
 * arrived. So these pin what the window is allowed to see.
 */
class TrendingTest extends DatabaseTestCase
{
    /** A tag no other test could be using. */
    private static function uniqueTag(): string
    {
        return 'trendtest' . bin2hex(random_bytes(5));
    }

    private static function remoteUser(): int
    {
        $user_id = self::createUser();

        DB::run('
UPDATE `Users`
    SET `remoteActorURI` = ?
    WHERE `userId` = ?
', 'si', 'https://elsewhere.test/users/' . $user_id, $user_id);

        return $user_id;
    }

    /** A top-level post carrying one hashtag, from elsewhere or from here. */
    private static function postTagged(int $user_id, string $tag, bool $remote): void
    {
        $text = 'a post about #' . $tag;
        $delta = (string) json_encode([['insert' => $text . "\n"]]);

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', $user_id, $text, $delta, $remote ? 'https://elsewhere.test/notes/' . bin2hex(random_bytes(6)) : null);
    }

    /** Whether the recompute put this tag in the trending list. */
    private static function isTrending(string $tag): bool
    {
        foreach (Trending::current(500) as $chip) {
            if ($chip -> type === 'hashtag' && mb_strtolower((string) $chip -> title) === $tag) {
                return true;
            }
        }

        return false;
    }

    /**
     * One recompute, three questions of it - the pass runs the whole window
     * through spaCy, so asking them separately would pay for that three times
     * to learn the same things.
     */
    public function testWhatTheTrendingWindowCounts(): void
    {
        // Enough voices, all of them from other servers. This is the case that
        // could not happen before and is the reason nothing ever trended.
        $from_elsewhere = self::uniqueTag();

        foreach (range(1, 3) as $ignored) {
            self::postTagged(self::remoteUser(), $from_elsewhere, true);
        }

        // One voice short of the bar, so repetition alone cannot make a topic.
        $too_few_voices = self::uniqueTag();

        foreach (range(1, 2) as $ignored) {
            self::postTagged(self::remoteUser(), $too_few_voices, true);
        }

        // Enough voices, but every one of them banned.
        $from_the_banned = self::uniqueTag();

        foreach (range(1, 3) as $ignored) {
            $banned_id = self::remoteUser();
            self::postTagged($banned_id, $from_the_banned, true);

            DB::run('
UPDATE `Users`
    SET `banned` = 1
    WHERE `userId` = ?
', 'i', $banned_id);
        }

        Trending::recompute();

        $this -> assertTrue(self::isTrending($from_elsewhere), 'posts from other servers are the conversation this server can hear');
        $this -> assertFalse(self::isTrending($too_few_voices), 'two voices is not a trend');
        $this -> assertFalse(self::isTrending($from_the_banned), 'a banned author is not one of the voices');
    }
}
