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

    /** What one topic's row says after a recompute. */
    private static function row(string $type, string $slug): ?object
    {
        return DB::row('
SELECT `popularity`, `postCount`, `computedAt`
    FROM `TrendingEntities`
    WHERE `type` = ? AND `slug` = ?
', 'stdClass', 'ss', $type, $slug);
    }

    /**
     * Popularity counts each post once, however many runs it stays in the
     * window for.
     *
     * The window is the newest N posts and runs overlap almost entirely, so a
     * total that simply added up each run's count would climb on its own while
     * nobody wrote anything - and the standing list is ordered by it.
     */
    public function testPopularityCountsAPostOnceHoweverOftenItIsRead(): void
    {
        $tag = self::uniqueTag();

        foreach (range(1, 3) as $ignored) {
            self::postTagged(self::createUser(), $tag, false);
        }

        Trending::recompute();
        $after_one = self::row('hashtag', $tag);

        $this -> assertNotNull($after_one, 'the topic qualified');
        $this -> assertSame(3, (int) $after_one -> popularity, 'three posts, counted once each');

        // Nothing new written, and the same three posts still in the window.
        Trending::recompute();
        $after_two = self::row('hashtag', $tag);

        $this -> assertSame(3, (int) $after_two -> popularity, 'and still three after reading them again');

        // One more post, which is the only thing that should move it.
        self::postTagged(self::createUser(), $tag, false);
        Trending::recompute();

        $this -> assertSame(4, (int) self::row('hashtag', $tag) -> popularity, 'a new post counts, once');
    }

    /**
     * A topic that stops trending stays in the table.
     *
     * It is what /topics/{type}/ lists, and a standing list that emptied itself
     * every time the conversation moved on would only ever repeat what the
     * front page already says.
     */
    public function testATopicOutlivesTheRunThatFoundIt(): void
    {
        $tag = self::uniqueTag();

        foreach (range(1, 3) as $ignored) {
            self::postTagged(self::createUser(), $tag, false);
        }

        Trending::recompute();
        $this -> assertTrue(self::isTrending($tag), 'trending on the run that found it');

        // The posts go, so the next run genuinely does not find this topic -
        // the same thing that happens when a conversation scrolls out of the
        // window, without writing WINDOW_SIZE posts to push it there.
        DB::run('
DELETE
    FROM `Posts`
    WHERE `description` = ?
', 's', 'a post about #' . $tag);

        Trending::recompute();

        $this -> assertFalse(self::isTrending($tag), 'no longer trending');
        $this -> assertNotNull(self::row('hashtag', $tag), 'but still kept');

        $listed = false;

        foreach (Trending::popularOfType('hashtag', 500, 0) as $chip) {
            $listed = $listed || mb_strtolower((string) $chip -> title) === $tag;
        }

        $this -> assertTrue($listed, 'and still on the standing list of its kind');
    }
}
