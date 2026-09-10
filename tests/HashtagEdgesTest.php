<?php

declare(strict_types=1);

class HashtagEdgesTest extends DatabaseTestCase
{
    public function testEdgesAreStoredUntilRecomputeAndDisappearingPairsAreRemoved(): void
    {
        $author = self::createUser();
        $prefix = 'edgetest' . bin2hex(random_bytes(6));
        $ops = [['insert' => '#' . $prefix . 'a #' . $prefix . "b\n"]];
        $posts = [];
        $tags = [];

        try {
            for ($i = 0; $i < 2; $i++) {
                DB::run('INSERT INTO `Posts` (`userId`, `descriptionDelta`) VALUES (?, ?)', 'is', $author, json_encode($ops));
                $posts[] = (int) mysqli_insert_id(DB::connection());
                Hashtag::indexPost(end($posts), $ops);
            }

            $tags = DB::rows('SELECT `hashtagId` FROM `Hashtags` WHERE `slug` IN (?, ?) ORDER BY `hashtagId`', 'HashtagNode', 'ss', $prefix . 'a', $prefix . 'b');
            $this -> assertCount(2, $tags);
            HashtagGraphList::recompute();
            $edges_for = new \ReflectionMethod(HashtagGraphList::class, 'edgesFor');
            $this -> assertSame([['a' => 1, 'b' => 0, 'weight' => 2]], $edges_for -> invoke(null, array_reverse($tags)));

            DB::run('DELETE FROM `Posts` WHERE `postId` = ?', 'i', $posts[0]);
            $this -> assertSame([['a' => 0, 'b' => 1, 'weight' => 2]], $edges_for -> invoke(null, $tags));
            HashtagGraphList::recompute();
            $this -> assertSame([['a' => 0, 'b' => 1, 'weight' => 1]], $edges_for -> invoke(null, $tags));

            DB::run('DELETE FROM `Posts` WHERE `postId` = ?', 'i', $posts[1]);
            HashtagGraphList::recompute();
            $this -> assertSame([], $edges_for -> invoke(null, $tags));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $author);
            DB::run('DELETE FROM `Hashtags` WHERE `slug` IN (?, ?)', 'ss', $prefix . 'a', $prefix . 'b');
        }
    }
}
