<?php

declare(strict_types=1);

/**
 * A profile's RSS feed carries that profile's own top-level posts, newest
 * first - not their replies, and not anybody else's posts.
 */
class UserRSSFeedTest extends DatabaseTestCase {
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

    /** @return int[] the post id each item links to */
    private static function postIdsInFeed(RSSFeed $feed): array {
        return array_map(static function (RSSItem $item): int {
            preg_match('~/(\d+)$~', $item -> link, $matches);

            return (int) ($matches[1] ?? 0);
        }, $feed -> items);
    }

    public function testCarriesOnlyTheProfilesOwnTopLevelPostsNewestFirst(): void {
        $author_id = self::createUser();
        $other_id = self::createUser();

        $first = self::createPost($author_id, 'First from the author');
        $second = self::createPost($author_id, 'Second from the author');
        self::createReply($author_id, $first);
        self::createPost($other_id, 'From someone else');

        $feed = new UserRSSFeed(['user' => User::loadMany([$author_id])[$author_id]]);

        $this -> assertSame([$second, $first], self::postIdsInFeed($feed));
    }
}
