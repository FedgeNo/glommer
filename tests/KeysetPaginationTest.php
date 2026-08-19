<?php

declare(strict_types=1);

class KeysetPaginationTest extends DatabaseTestCase
{
    private static function post(int $user_id): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', $user_id, 'keyset', json_encode(['ops' => [['insert' => "keyset\n"]]]));

        return (int) mysqli_insert_id(DB::connection());
    }

    /** @param Post[] $posts */
    private static function ids(array $posts): array
    {
        return array_map(static fn (Post $post): int => (int) $post -> postId, $posts);
    }

    public function testGlobalPagesIgnorePostsInsertedAfterTheCursor(): void
    {
        $author_id = self::createUser();
        $created = [];

        for ($index = 0; $index < 22; $index++) {
            $created[] = self::post($author_id);
        }

        $first = new GlobalFeedList();
        $first_page = $first -> toJSON();
        $first_ids = self::ids($first_page['items']);
        $inserted_later = self::post($author_id);
        $cursor = $first_page['cursor'];
        $second = new GlobalFeedList(['beforePostId' => $cursor['postId']]);
        $second_ids = self::ids($second -> items);

        $this -> assertSame([], array_values(array_intersect($first_ids, $second_ids)), 'pages do not overlap');
        $this -> assertFalse(in_array($inserted_later, $second_ids, true), 'a new head row does not shift the next page');
        $this -> assertTrue(in_array($created[0], $second_ids, true));
        $this -> assertTrue(in_array($created[1], $second_ids, true));
    }

    public function testFriendsPagesUsePostIdToBreakEqualTimestamps(): void
    {
        $reader_id = self::createUser();
        $author_id = self::createUser();
        $sort_at = '2026-01-01 12:00:00';
        $created = [];

        for ($index = 0; $index < 22; $index++) {
            $post_id = self::post($author_id);
            $created[] = $post_id;
            DB::run('
INSERT INTO `Timelines` (`userId`, `postId`, `sortAt`)
    VALUES (?, ?, ?)
', 'iis', $reader_id, $post_id, $sort_at);
        }

        $previous_user_id = $_SESSION['userId'] ?? null;
        $_SESSION['userId'] = $reader_id;
        Auth::clearUserCache();

        try {
            $first = new FriendsFeedList(['userId' => $reader_id]);
            $first_page = $first -> toJSON();
            $first_ids = self::ids($first_page['items']);
            $cursor = $first_page['cursor'];

            $inserted_later = self::post($author_id);
            DB::run('
INSERT INTO `Timelines` (`userId`, `postId`, `sortAt`)
    VALUES (?, ?, ?)
', 'iis', $reader_id, $inserted_later, $sort_at);

            $second = new FriendsFeedList([
                'userId' => $reader_id,
                'beforeSortAt' => $cursor['sortAt'],
                'beforePostId' => $cursor['postId'],
            ]);
            $second_ids = self::ids($second -> items);

            $this -> assertSame([], array_values(array_intersect($first_ids, $second_ids)), 'equal timestamps do not duplicate rows');
            $this -> assertFalse(in_array($inserted_later, $second_ids, true));
            $this -> assertTrue(in_array($created[0], $second_ids, true));
            $this -> assertTrue(in_array($created[1], $second_ids, true));
        } finally {
            if ($previous_user_id === null) {
                unset($_SESSION['userId']);
            } else {
                $_SESSION['userId'] = $previous_user_id;
            }

            Auth::clearUserCache();
        }
    }
}
