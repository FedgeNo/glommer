<?php

declare(strict_types=1);

/**
 * Filling in the language of every post rather than of some.
 *
 * The reading itself is langdetect's, in bin/ner-extract.py, and needs the
 * Python environment - which a dev install deliberately does not have. What is
 * checkable everywhere is the part that decides which posts get looked at and
 * what is done with the answers, since that is what made two thirds of the
 * posts here unreadable: they were never selected in the first place.
 */
class LanguageDetectorTest extends DatabaseTestCase
{
    private static function post(int $user_id, string $text, ?string $detected): int
    {
        $delta = (string) json_encode([['insert' => $text . "\n"]]);

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `detectedLanguage`)
    VALUES (?, ?, ?, ?)
', 'isss', $user_id, $text, $delta, $detected);

        return (int) mysqli_insert_id(DB::connection());
    }

    /**
     * A post nobody has read the language of is one this picks up, whatever
     * kind of post it is.
     *
     * The trending pass only ever looked at top-level posts from accounts that
     * are not bots, inside its own window - so a reply, or anything a bot
     * wrote, had no language and never would. Selection is the whole of what
     * went wrong, so selection is what this pins.
     */
    public function testAPostWithNoLanguageIsSelectedWhateverKindItIs(): void
    {
        $user = self::createUser();

        $reply_id = self::post($user, 'A reply, which trending never read', null);
        $read_id = self::post($user, 'Already read', 'en');

        $waiting = DB::rows('
SELECT `postId`
    FROM `Posts`
    WHERE `detectedLanguage` IS NULL
', 'stdClass');

        $ids = array_map(static fn (object $row): int => (int) $row -> postId, $waiting);

        $this -> assertTrue(in_array($reply_id, $ids, true), 'the unread post is waiting');
        $this -> assertFalse(in_array($read_id, $ids, true), 'the read one is not asked again');
    }

    /**
     * Nothing happens where there is no detector, rather than posts being
     * marked as read with nothing behind it.
     *
     * A dev install has no Python environment, which is exactly the state this
     * has to be safe in: it must leave the backlog alone for a server that
     * does have one to work through.
     */
    public function testWithoutTheEnvironmentNothingIsWrittenDown(): void
    {
        if (LanguageDetector::isAvailable()) {
            throw new TestSkippedException('needs an install without the detector - this one has it');
        }

        $user = self::createUser();
        $post_id = self::post($user, 'Something in some language', null);

        $this -> assertSame(0, LanguageDetector::fillInBatch());

        $post = DB::row('
SELECT `detectedLanguage`
    FROM `Posts`
    WHERE `postId` = ?
', 'stdClass', 'i', $post_id);

        $this -> assertNull($post -> detectedLanguage, 'still waiting to be read');
    }

    /**
     * What a post is written in outranks what it says it is.
     *
     * A declared language is the sender's account setting rather than a fact
     * about the post - somebody who set it once and wrote in three languages
     * declares all three the same.
     */
    public function testWhatWasReadBeatsWhatWasDeclared(): void
    {
        $user = self::createUser();
        $post_id = self::post($user, 'Ceci est écrit en français', 'fr');

        DB::run('
UPDATE `Posts`
    SET `language` = ?
    WHERE `postId` = ?
', 'si', 'en', $post_id);

        $source = new \ReflectionMethod(PostTranslation::class, 'sourceLanguage');
        $source -> setAccessible(true);

        $this -> assertSame('fr', $source -> invoke(null, $post_id), 'the words win');
    }

    /** With nothing read, the declaration is all there is, so it stands in. */
    public function testADeclarationStandsInUntilSomethingHasRead(): void
    {
        $user = self::createUser();
        $post_id = self::post($user, 'Nothing has read this yet', null);

        DB::run('
UPDATE `Posts`
    SET `language` = ?
    WHERE `postId` = ?
', 'si', 'de', $post_id);

        $source = new \ReflectionMethod(PostTranslation::class, 'sourceLanguage');
        $source -> setAccessible(true);

        $this -> assertSame('de', $source -> invoke(null, $post_id));
    }
}
