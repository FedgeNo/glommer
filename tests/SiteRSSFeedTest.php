<?php

declare(strict_types=1);

/**
 * The site-wide RSS feed carries the same public selection the home page's
 * global feed shows: top-level posts by non-banned local authors, and nothing
 * else - no replies, no banned author's posts, no remote-origin posts.
 */
class SiteRSSFeedTest extends DatabaseTestCase {
    private static function createPost(int $user_id, string $description): int {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`)
    VALUES (?, ?)
', 'is', $user_id, $description);

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function createReply(int $user_id, int $parent_id): int {
        DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `description`)
    VALUES (?, ?, ?)
', 'iis', $user_id, $parent_id, 'reply ' . bin2hex(random_bytes(4)));

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function createRemotePost(int $user_id): int {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `remoteObjectURI`)
    VALUES (?, ?, ?)
', 'iss', $user_id, 'remote content', 'https://remote.test/notes/' . bin2hex(random_bytes(6)));

        return (int) mysqli_insert_id(DB::connection());
    }

    private static function createBannedUser(): int {
        $unique = bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `banned`)
    VALUES (?, ?, ?, ?)
', 'sssi', 'test-' . $unique, 'test-' . $unique . '@example.test', password_hash($unique, PASSWORD_DEFAULT), 1);

        return (int) mysqli_insert_id(DB::connection());
    }

    /** @return int[] the post id each item links to */
    private static function postIdsInFeed(RSSFeed $feed): array {
        return array_map(static function (RSSItem $item): int {
            preg_match('~/(\d+)$~', $item -> link, $matches);

            return (int) ($matches[1] ?? 0);
        }, $feed -> items);
    }

    public function testShowsLocalTopLevelPostsAndHidesRepliesBannedAndRemote(): void {
        $author_id = self::createUser();
        $banned_id = self::createBannedUser();

        // Created last, so among the newest ids - a leaked one would sit at the
        // front of the page rather than fall off its end.
        $visible = self::createPost($author_id, 'Visible on the site feed');
        $reply = self::createReply($author_id, $visible);
        $banned_post = self::createPost($banned_id, 'From a banned author');
        $remote_post = self::createRemotePost($author_id);

        $ids = self::postIdsInFeed(new SiteRSSFeed());

        $this -> assertTrue(in_array($visible, $ids, true), 'a local top-level post belongs in the site feed');
        $this -> assertFalse(in_array($reply, $ids, true), 'a reply does not belong in the site feed');
        $this -> assertFalse(in_array($banned_post, $ids, true), 'a banned author\'s post does not belong in the site feed');
        $this -> assertFalse(in_array($remote_post, $ids, true), 'a remote post does not belong in the site feed');
    }
}
