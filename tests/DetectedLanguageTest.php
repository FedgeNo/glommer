<?php

declare(strict_types=1);

/**
 * What language a post is actually in, as against what its sender said.
 *
 * The declared value cannot be used for this: a Fediverse sender fills it from
 * their account setting, so an account set to English writing in French says
 * English. Which model reads a post is decided on the words, and since the
 * answer has to be worked out anyway it is worth keeping.
 *
 * These need the NER environment - the detector lives in it - so they stand
 * down where it is not installed rather than failing about the machine.
 */
class DetectedLanguageTest extends DatabaseTestCase
{
    private function requireExtractor(): void
    {
        $python = new \ReflectionClassConstant(EntityExtractor::class, 'NER_PYTHON');

        if (!is_executable((string) $python -> getValue())) {
            throw new TestSkippedException('needs the NER environment - run bin/install.php');
        }
    }

    /** @return array<int, ?string> */
    private static function languagesFor(string ...$texts): array
    {
        EntityExtractor::extractBatch(array_map(
            static fn (string $text): string => json_encode(['ops' => [['insert' => $text . "\n"]]]),
            $texts
        ));

        return EntityExtractor::detectedLanguages();
    }

    public function testEachPostIsReadInTheLanguageItIsWrittenIn(): void
    {
        $this -> requireExtractor();

        $found = self::languagesFor(
            'Der Bundestag hat heute in Berlin über den Haushalt abgestimmt worden.',
            'Le trafic est interrompu entre Nation et Vincennes ce matin.',
            'Microsoft announced a new version of Windows in Seattle yesterday.'
        );

        $this -> assertSame('de', $found[0]);
        $this -> assertSame('fr', $found[1]);
        $this -> assertSame('en', $found[2]);
    }

    /**
     * Too little to read is answered with nothing rather than a guess - and
     * nothing is what routes the text to no model at all, which is the safe
     * end of the choice.
     */
    public function testTooLittleToReadIsNotGuessedAt(): void
    {
        $this -> requireExtractor();

        $found = self::languagesFor('ok', '👍', 'https://example.test/');

        $this -> assertNull($found[0]);
        $this -> assertNull($found[1]);
        $this -> assertNull($found[2]);
    }

    /**
     * German capitalises every noun, so an English model reads them all as
     * proper names. This is the case the whole change exists for.
     */
    public function testGermanCommonNounsAreNotTakenForNames(): void
    {
        $this -> requireExtractor();

        $entities = EntityExtractor::extractBatch([
            json_encode(['ops' => [['insert' => "Der Bundestag hat heute in Berlin über den Haushalt abgestimmt worden.\n"]]]),
        ]);

        $values = array_map(static fn (array $entity): string => (string) $entity['value'], $entities[0]);

        $this -> assertTrue(in_array('Berlin', $values, true), 'a real place is still found');
        $this -> assertFalse(in_array('Haushalt', $values, true), 'an ordinary noun is not a name');
    }

    /** A Finnish function word is what the English model was storing as a topic. */
    public function testAFinnishFunctionWordIsNotATopic(): void
    {
        $this -> requireExtractor();

        $entities = EntityExtractor::extractBatch([
            json_encode(['ops' => [['insert' => "Helsingin kaupunki aloittaa uuden hankkeen. Tämä on tärkeää kaikille asukkaille.\n"]]]),
        ]);

        $values = array_map(static fn (array $entity): string => (string) $entity['value'], $entities[0]);

        $this -> assertFalse(in_array('Tämä', $values, true));
        $this -> assertFalse(in_array('kaikille', $values, true));
    }

    /**
     * Every kind stored has a page. The non-English models name a person PER
     * where the English one says PERSON, and a topic is addressed by its type
     * - so an untranslated label would be a row whose own page is a 404.
     */
    public function testNothingIsStoredUnderAKindWithNoPage(): void
    {
        $this -> requireExtractor();

        $batch = EntityExtractor::extractBatch([
            json_encode(['ops' => [['insert' => "Angela Merkel hat in Berlin mit Emmanuel Macron gesprochen.\n"]]]),
            json_encode(['ops' => [['insert' => "Sanna Marin tapasi Helsingissä presidentti Sauli Niinistön.\n"]]]),
            json_encode(['ops' => [['insert' => "Microsoft announced a new version of Windows in Seattle yesterday.\n"]]]),
        ]);

        $types = [];

        foreach ($batch as $entities) {
            foreach ($entities as $entity) {
                $types[(string) $entity['type']] = true;
            }
        }

        $this -> assertTrue($types !== [], 'the sample produces entities at all');

        foreach (array_keys($types) as $type) {
            $this -> assertTrue(
                EntityType::isKnown($type),
                $type . ' is stored but /topics/' . $type . '/ is a 404'
            );
        }
    }

    /** The answer is written onto the post, so it can be read without asking again. */
    public function testTheAnswerIsKeptOnThePost(): void
    {
        $this -> requireExtractor();

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', self::createUser(), 'german',
            json_encode(['ops' => [['insert' => "Der Bundestag hat heute in Berlin über den Haushalt abgestimmt worden.\n"]]]));

        $post_id = (int) mysqli_insert_id(DB::connection());

        Trending::recompute();

        $stored = DB::row('
SELECT `detectedLanguage`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        $this -> assertSame('de', $stored ?-> detectedLanguage);
    }
}
