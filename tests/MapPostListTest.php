<?php

declare(strict_types=1);

class MapPostListTest extends DatabaseTestCase
{
    private static function locatedPost(?string $remote_object_uri): int
    {
        $user_id = self::createUser();

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `remoteObjectURI`)
    VALUES (?, ?, ?)
', 'iss', $user_id, 'mapped', $remote_object_uri);

        $post_id = (int) mysqli_insert_id(DB::connection());

        DB::run('
INSERT INTO `PostLocations` (`postId`, `latitude`, `longitude`)
    VALUES (?, ?, ?)
', 'idd', $post_id, 49.28, -123.09);

        return $post_id;
    }

    public function testRemotePostsAreExcludedFromThePublicMap(): void
    {
        $local_id = self::locatedPost(null);
        $remote_id = self::locatedPost('https://remote.example/posts/' . bin2hex(random_bytes(4)));

        $public_ids = array_map(static fn (MapPost $post): int => (int) $post -> postId, new MapPostList(false) -> items);
        $member_ids = array_map(static fn (MapPost $post): int => (int) $post -> postId, new MapPostList(true) -> items);

        $this -> assertTrue(in_array($local_id, $public_ids, true));
        $this -> assertFalse(in_array($remote_id, $public_ids, true));
        $this -> assertTrue(in_array($remote_id, $member_ids, true));
    }
}
