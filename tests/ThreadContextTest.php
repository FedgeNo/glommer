<?php

declare(strict_types=1);

/**
 * The line over a reply saying what it answers.
 *
 * A reply arrives in a feed on its own - a relay carries other people's
 * threads, mid-conversation, with nothing above them - so the card has to say
 * what it is part of and offer the way back to the start. The walk that finds
 * both has to stay one query however many replies are on the page, or a feed
 * of them costs a query per card.
 */
class ThreadContextTest extends DatabaseTestCase
{
    private static function post(int $user_id, ?int $parent_id, ?string $title, string $body): int
    {
        $delta = (string) json_encode([['insert' => $body . "\n"]]);

        DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `title`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?, ?, ?)
', 'iisss', $user_id, $parent_id, $title, $body, $delta);

        return (int) mysqli_insert_id(DB::connection());
    }

    /** A thread of $depth posts, oldest first. */
    private static function thread(int $depth): array
    {
        $author = self::createUser();
        $ids = [self::post($author, null, 'Where it began', 'the opening post')];

        for ($i = 1; $i < $depth; $i++) {
            $ids[] = self::post(self::createUser(), $ids[$i - 1], null, 'reply number ' . $i);
        }

        return $ids;
    }

    private static function load(int $post_id): Post
    {
        return DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);
    }

    public function testAReplyIsToldWhatItAnswersAndWhereItStarted(): void
    {
        $ids = self::thread(4);
        $last = self::load($ids[3]);

        $context = ThreadContext::forPosts([$last])[$ids[3]] ?? null;

        $this -> assertNotNull($context);
        $this -> assertSame($ids[2], $context -> parentId, 'the post directly above it');
        $this -> assertSame($ids[0], $context -> rootId, 'and the one the thread began with');
    }

    /**
     * A direct reply's parent IS the start, and one line does not carry two
     * links to the same post.
     */
    public function testADirectReplyIsOfferedNoSeparateStart(): void
    {
        $ids = self::thread(2);
        $context = ThreadContext::forPosts([self::load($ids[1])])[$ids[1]];

        $this -> assertSame($ids[0], $context -> parentId);
        $this -> assertSame($ids[0], $context -> rootId);

        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        HTMLObject::currentDocument() -> appendChild($context -> toDOM());

        $links = new \DOMXPath(HTMLObject::currentDocument()) -> query('//a');

        $this -> assertSame(1, $links -> length, 'the parent link already goes to the start');
    }

    /** A post that starts a thread is not told it is answering anything. */
    public function testAPostThatStartsAThreadHasNoLine(): void
    {
        $ids = self::thread(2);

        $this -> assertSame([], ThreadContext::forPosts([self::load($ids[0])]));
    }

    /** The whole page in one query, however many replies are on it. */
    public function testAFeedOfRepliesCostsOneQuery(): void
    {
        $ids = self::thread(6);

        $posts = array_map(static fn (int $id): Post => self::load($id), array_slice($ids, 1));

        $before = DB::queryCount();
        $contexts = ThreadContext::forPosts($posts);

        $this -> assertSame($before + 1, DB::queryCount(), 'one walk covers the page');
        $this -> assertCount(5, $contexts);

        foreach ($contexts as $context) {
            $this -> assertSame($ids[0], $context -> rootId);
        }
    }

    /** What it says, and the way out of the middle of the thread. */
    public function testTheLineNamesTheParentAndOffersTheStart(): void
    {
        $ids = self::thread(4);
        $context = ThreadContext::forPosts([self::load($ids[3])])[$ids[3]];

        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        $element = $context -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        $xpath = new \DOMXPath(HTMLObject::currentDocument());
        $markup = (string) HTMLObject::currentDocument() -> saveHTML($element);

        $this -> assertTrue(str_contains($markup, 'In response to'));
        $this -> assertSame(1, $xpath -> query('//a[contains(@class, "ThreadStartLink")]') -> length);
        $this -> assertTrue(
            str_contains((string) $xpath -> query('//a[contains(@class, "ThreadStartLink")]') -> item(0) -> getAttribute('href'), '/' . $ids[0]),
            'the start link points at the first post'
        );
    }

    /**
     * A parent with no title of its own is named by its opening words rather
     * than by nothing at all.
     */
    public function testAnUntitledParentIsNamedByItsWords(): void
    {
        $author = self::createUser();
        $parent = self::post($author, null, null, 'a post with no title but plenty to say');
        $reply = self::post(self::createUser(), $parent, null, 'answering it');

        $context = ThreadContext::forPosts([self::load($reply)])[$reply];

        $this -> assertSame('a post with no title but plenty to say', $context -> parentLabel);
    }
}
