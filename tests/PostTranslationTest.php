<?php

declare(strict_types=1);

/**
 * The cache and the language gate around post translation. The model call
 * itself stays out of reach - no key is configured under test, and a feature
 * that spends tokens must degrade to "no answer", never to an error.
 */
class PostTranslationTest extends DatabaseTestCase
{
    private function post(): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', self::createUser(), 'bonjour tout le monde', json_encode([['insert' => "bonjour tout le monde\n"]]));

        return (int) mysqli_insert_id(DB::connection());
    }

    public function testALanguageTagNormalizes(): void
    {
        $this -> assertSame('en', PostTranslation::normalizeLanguage(' EN '));
        $this -> assertSame('pt-br', PostTranslation::normalizeLanguage('pt-BR'));
        $this -> assertSame('zh-hant', PostTranslation::normalizeLanguage('zh-Hant'));
    }

    public function testRubbishIsRefusedNotPassedToAModel(): void
    {
        // Anything that is not shaped like a tag would otherwise ride into
        // the prompt as free text.
        foreach (['', 'x', 'english please', 'en;drop', str_repeat('a', 40), '-en', 'en-'] as $bad) {
            $this -> assertNull(PostTranslation::normalizeLanguage($bad), $bad . ' should be refused');
        }
    }

    /**
     * The configured model is usually the free-models router, and some of what
     * it picks classifies a message rather than translating it. A verdict
     * stored as a translation is the answer that post gets forever, so it is
     * recognised for what it is and never becomes one.
     */
    public function testAVerdictInsteadOfATranslationCountsAsNoTranslation(): void
    {
        $this -> assertSame('', PostTranslation::translationFrom('User Safety: safe'));
        $this -> assertSame('', PostTranslation::translationFrom('safety: SAFE'));
    }

    public function testAVerdictAlongsideARealTranslationIsTakenOffIt(): void
    {
        $newline = chr(10);

        $this -> assertSame(
            'hello everyone',
            PostTranslation::translationFrom('User Safety: safe' . $newline . $newline . 'hello everyone')
        );

        $this -> assertSame(
            'hello everyone',
            PostTranslation::translationFrom('hello everyone' . $newline . 'Classification: benign')
        );
    }

    /** Ordinary words are left exactly as they came, line breaks and all. */
    public function testATranslationIsNotTrimmedOfItsOwnLines(): void
    {
        $newline = chr(10);
        $translation = 'hello everyone' . $newline . 'and good evening' . $newline . $newline . 'a second paragraph';

        $this -> assertSame($translation, PostTranslation::translationFrom($translation));
    }

    public function testACachedTranslationReadsBackByPostAndLanguage(): void
    {
        $post_id = $this -> post();

        DB::run('
INSERT INTO `PostTranslations` (`postId`, `language`, `body`)
    VALUES (?, ?, ?)
', 'iss', $post_id, 'en', 'hello everyone');

        $this -> assertSame('hello everyone', PostTranslation::cached($post_id, 'en'));
        $this -> assertNull(PostTranslation::cached($post_id, 'de'));
    }

    public function testWithoutAKeyTranslationIsNoAnswerAndNoRow(): void
    {
        $post_id = $this -> post();

        $this -> assertNull(PostTranslation::translate($post_id, 'en', 'bonjour tout le monde'));
        $this -> assertNull(PostTranslation::cached($post_id, 'en'));
    }

    public function testTranslationsDieWithTheirPost(): void
    {
        // Through the foreign key - the cache must never outlive what it is
        // a translation of.
        $post_id = $this -> post();

        DB::run('
INSERT INTO `PostTranslations` (`postId`, `language`, `body`)
    VALUES (?, ?, ?)
', 'iss', $post_id, 'en', 'hello everyone');

        DB::run('
DELETE
    FROM `Posts`
    WHERE `postId` = ?
', 'i', $post_id);

        $this -> assertNull(PostTranslation::cached($post_id, 'en'));
    }
}
