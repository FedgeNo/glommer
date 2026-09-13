<?php

declare(strict_types=1);

class NestedPostVisibilityTest extends DatabaseTestCase
{
    private function post(int $author, ?int $parent = null, ?int $quote = null, ?string $remote = null): Post
    {
        DB::run('INSERT INTO `Posts` (`userId`, `parentId`, `quotedPostId`, `remoteObjectURI`, `description`)
            VALUES (?, ?, ?, ?, ?)', 'iiiss', $author, $parent, $quote, $remote, 'Visible only to the right viewer');
        return DB::row('SELECT * FROM `Posts` WHERE `postId` = ?', 'Post', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    public function testAnonymousNestedViewsOmitRemoteContentAndRemoteParents(): void
    {
        $session = $_SESSION ?? [];
        try {
            $local = self::createUser();
            $remote = self::createUser();
            DB::run('UPDATE `Users` SET `remoteActorURI` = ? WHERE `userId` = ?', 'si',
                'https://remote.invalid/users/' . $remote, $remote);
            $parent = $this -> post($local);
            $local_reply = $this -> post($local, $parent -> postId);
            $remote_reply = $this -> post($remote, $parent -> postId, null, 'https://remote.invalid/posts/' . bin2hex(random_bytes(6)));
            $reply_to_remote = $this -> post($local, $remote_reply -> postId);
            $quote = $this -> post($local, null, $remote_reply -> postId);

            $_SESSION = [];
            $this -> assertSame([$local_reply -> postId], array_map(static fn (Post $post): int => $post -> postId,
                (new ReplyList(['parentId' => $parent -> postId])) -> items));
            $this -> assertSame([], (new ReplyList(['parentId' => $remote_reply -> postId])) -> items);
            $this -> assertSame([], QuotedPost::forPosts([$quote]));
            $this -> assertSame([], ThreadContext::forPosts([$reply_to_remote]));

            $_SESSION = ['userId' => $local];
            Auth::clearUserCache();
            $this -> assertCount(2, (new ReplyList(['parentId' => $parent -> postId])) -> items);
            $this -> assertCount(1, (new ReplyList(['parentId' => $remote_reply -> postId])) -> items);
            $this -> assertCount(1, QuotedPost::forPosts([$quote]));
            $this -> assertSame($remote_reply -> postId, ThreadContext::forPosts([$reply_to_remote])[$reply_to_remote -> postId] -> parentId);
        } finally {
            $_SESSION = $session;
            Auth::clearUserCache();
        }
    }
}
