<?php

declare(strict_types=1);

/**
 * The quote reference and its embed: attached per page like every other
 * post attachment, absent exactly when the quoted material shouldn't stand -
 * deleted, or its author banned - while the commentary above it survives
 * either way.
 */
class QuotedPostTest extends DatabaseTestCase
{
    private function post(int $user_id, ?int $quoted_post_id = null): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `quotedPostId`)
    VALUES (?, ?, ?, ?)
', 'issi', $user_id, 'words', json_encode([['insert' => "words\n"]]), $quoted_post_id);

        return (int) mysqli_insert_id(DB::connection());
    }

    private function hydrated(int $post_id): Post
    {
        $post = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        return Post::fromRowsWithItems([$post])[0];
    }

    public function testAQuoteCarriesItsQuotedPost(): void
    {
        $quoted_id = $this -> post(self::createUser());
        $quoting_id = $this -> post(self::createUser(), $quoted_id);

        $quoted = $this -> hydrated($quoting_id) -> quotedPost;

        $this -> assertNotNull($quoted);
        $this -> assertSame($quoted_id, (int) $quoted -> postId);
        $this -> assertSame('words', $quoted -> description);
    }

    /**
     * Two posts quoting the same one are two embeds to draw, and drawing an
     * HTMLObject is a one-shot act. Handed the same instance twice, the second
     * render throws and takes the whole feed with it - which is how a page
     * carrying both went down.
     */
    public function testTwoPostsQuotingTheSameOneEachGetTheirOwnEmbed(): void
    {
        $quoted_id = $this -> post(self::createUser());
        $first_id = $this -> post(self::createUser(), $quoted_id);
        $second_id = $this -> post(self::createUser(), $quoted_id);

        $posts = Post::fromRowsWithItems(DB::rows('
SELECT *
    FROM `Posts`
    WHERE `postId` IN (?, ?)
', 'Post', 'ii', $first_id, $second_id));

        $this -> assertCount(2, $posts);
        $this -> assertNotNull($posts[0] -> quotedPost);
        $this -> assertNotNull($posts[1] -> quotedPost);

        // The same post quoted, and not the same object twice.
        $this -> assertSame(
            (int) $posts[0] -> quotedPost -> postId,
            (int) $posts[1] -> quotedPost -> postId
        );
        $this -> assertFalse(
            $posts[0] -> quotedPost === $posts[1] -> quotedPost,
            'each quoting post needs an embed of its own to render'
        );
    }

    public function testDeletingTheQuotedPostLeavesTheCommentaryStanding(): void
    {
        $quoted_id = $this -> post(self::createUser());
        $quoting_id = $this -> post(self::createUser(), $quoted_id);

        DB::run('
DELETE
    FROM `Posts`
    WHERE `postId` = ?
', 'i', $quoted_id);

        // The foreign key SET NULLs the reference; the quote renders as an
        // ordinary post.
        $survivor = $this -> hydrated($quoting_id);

        $this -> assertNull($survivor -> quotedPost);
        $this -> assertSame('words', $survivor -> description);
    }

    public function testABannedAuthorsPostIsNotEmbedded(): void
    {
        $banned_author = self::createUser();
        $quoted_id = $this -> post($banned_author);
        $quoting_id = $this -> post(self::createUser(), $quoted_id);

        DB::run('
UPDATE `Users`
    SET `banned` = 1
    WHERE `userId` = ?
', 'i', $banned_author);

        $this -> assertNull($this -> hydrated($quoting_id) -> quotedPost);
    }

    public function testTheOutboundNoteNamesWhatItQuotes(): void
    {
        $author = User::load(self::createUser());
        $quoted_id = $this -> post(self::createUser());
        $quoting_id = $this -> post((int) $author -> userId, $quoted_id);

        $post = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $quoting_id);

        $document = ActivityPubNote::document($post, $author);

        $this -> assertNotNull($document['quoteUrl'] ?? null);
        $this -> assertSame($document['quoteUrl'], $document['_misskey_quote']);

        $fep_links = array_filter($document['tag'] ?? [], static fn (array $tag): bool => ($tag['type'] ?? '') === 'Link');

        $this -> assertCount(1, array_values($fep_links));
    }
}
