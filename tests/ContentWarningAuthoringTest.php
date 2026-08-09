<?php

declare(strict_types=1);

/**
 * Writing a warning here, and sending one out.
 *
 * The rule the composer and the edit form both apply is that a warning only
 * exists alongside the mark - a stored warning on an unflagged post is a gate
 * with nothing behind it, and the renderer would put the body behind words the
 * author had withdrawn.
 */
class ContentWarningAuthoringTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    private static function post(int $user_id, int $sensitive, ?string $warning): Post
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `sensitive`, `contentWarning`)
    VALUES (?, ?, ?, ?, ?)
', 'issis', $user_id, 'words', json_encode([['insert' => "words\n"]]), $sensitive, $warning);

        return DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    /** A post of ours carries its warning to the network the way the network reads one. */
    public function testAWarnedPostSaysSoOnTheWire(): void
    {
        $author = self::localUser();
        $post = self::post((int) $author -> userId, 1, 'Spoilers for the finale');

        $document = ActivityPubNote::document(Post::fromRowWithItems($post), $author);

        $this -> assertSame('Spoilers for the finale', $document['summary'] ?? null);
        $this -> assertTrue($document['sensitive']);
    }

    /**
     * And an unwarned one says nothing. An empty summary is a content warning
     * to every receiver that checks for the key rather than its contents.
     */
    public function testAnUnwarnedPostSendsNoSummary(): void
    {
        $author = self::localUser();
        $post = self::post((int) $author -> userId, 0, null);

        $document = ActivityPubNote::document(Post::fromRowWithItems($post), $author);

        $this -> assertFalse(isset($document['summary']));
    }

    /**
     * The warning is not the title. `name` is what a post is called and
     * `summary` is what to know before reading it; sending one as the other
     * turns a spoiler into a headline.
     */
    public function testTheWarningIsNotSentAsTheTitle(): void
    {
        $author = self::localUser();
        $post = self::post((int) $author -> userId, 1, 'Spoilers');

        $document = ActivityPubNote::document(Post::fromRowWithItems($post), $author);

        $this -> assertSame('Spoilers', $document['summary'] ?? null);
        $this -> assertFalse(isset($document['name']), 'a warning does not give the post a name');
    }

    /** A warned post of ours gates its own body, the same as one that arrived. */
    public function testAWarnedPostOfOursGatesItsOwnBody(): void
    {
        $author = self::localUser();
        $post = Post::fromRowWithItems(self::post((int) $author -> userId, 1, 'Spoilers'));

        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        HTMLObject::currentDocument() -> appendChild($post -> toDOM());

        $xpath = new \DOMXPath(HTMLObject::currentDocument());
        $gate = $xpath -> query('//details[contains(@class, "ContentWarning")]');

        $this -> assertSame(1, $gate -> length);
        $this -> assertTrue(str_contains((string) $gate -> item(0) ?-> textContent, 'words'));
    }
}
