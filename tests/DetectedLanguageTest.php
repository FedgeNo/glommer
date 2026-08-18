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
    /**
     * The whole class's texts, read in one go.
     *
     * Every extraction is a subprocess that imports spaCy and loads a model
     * per language in the batch - four and a half seconds before it reads a
     * word. Asking once and having each test look at its own line costs that
     * once rather than once per test, which is the difference between this
     * class taking five seconds and taking a minute.
     */
    private const CORPUS = [
        'german' => 'Der Bundestag hat heute in Berlin über den Haushalt abgestimmt worden.',
        'french' => 'Le trafic est interrompu entre Nation et Vincennes ce matin.',
        'english' => 'Microsoft announced a new version of Windows in Seattle yesterday.',
        'finnish' => 'Helsingin kaupunki aloittaa uuden hankkeen. Tämä on tärkeää kaikille asukkaille.',
        'germanNames' => 'Angela Merkel hat in Berlin mit Emmanuel Macron gesprochen.',
        'finnishNames' => 'Sanna Marin tapasi Helsingissä presidentti Sauli Niinistön.',
        'tooShort' => 'ok',
        'justEmoji' => '👍',
        'justALink' => 'https://example.test/',
    ];

    /** @var array{entities: array<string, array>, languages: array<string, ?string>}|null */
    private static ?array $read = null;

    private function requireExtractor(): void
    {
        $python = new \ReflectionClassConstant(EntityExtractor::class, 'NER_PYTHON');

        if (!is_executable((string) $python -> getValue())) {
            throw new TestSkippedException('needs the NER environment - run bin/install.php');
        }
    }

    /** @return array{entities: array<string, array>, languages: array<string, ?string>} */
    private function read(): array
    {
        $this -> requireExtractor();

        if (self::$read === null) {
            $names = array_keys(self::CORPUS);

            $entities = EntityExtractor::extractBatch(array_map(
                static fn (string $text): string => json_encode(['ops' => [['insert' => $text . "\n"]]]),
                array_values(self::CORPUS)
            ));

            self::$read = [
                'entities' => array_combine($names, $entities),
                'languages' => array_combine($names, EntityExtractor::detectedLanguages()),
            ];
        }

        return self::$read;
    }

    /** @return string[] the entity values found in one of the corpus texts */
    private function valuesIn(string $name): array
    {
        return array_map(
            static fn (array $entity): string => (string) $entity['value'],
            $this -> read()['entities'][$name]
        );
    }

    public function testEachPostIsReadInTheLanguageItIsWrittenIn(): void
    {
        $languages = $this -> read()['languages'];

        $this -> assertSame('de', $languages['german']);
        $this -> assertSame('fr', $languages['french']);
        $this -> assertSame('en', $languages['english']);
        $this -> assertSame('fi', $languages['finnish']);
    }

    /**
     * Too little to read is answered with nothing rather than a guess - and
     * nothing is what routes the text to no model at all, which is the safe
     * end of the choice.
     */
    public function testTooLittleToReadIsNotGuessedAt(): void
    {
        $languages = $this -> read()['languages'];

        $this -> assertNull($languages['tooShort']);
        $this -> assertNull($languages['justEmoji']);
        $this -> assertNull($languages['justALink']);
    }

    /**
     * German capitalises every noun, so an English model reads them all as
     * proper names. This is the case the whole change exists for.
     */
    public function testGermanCommonNounsAreNotTakenForNames(): void
    {
        $values = $this -> valuesIn('german');

        $this -> assertTrue(in_array('Berlin', $values, true), 'a real place is still found');
        $this -> assertFalse(in_array('Haushalt', $values, true), 'an ordinary noun is not a name');
    }

    /** A Finnish function word is what the English model was storing as a topic. */
    public function testAFinnishFunctionWordIsNotATopic(): void
    {
        $values = $this -> valuesIn('finnish');

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
        $types = [];

        foreach ($this -> read()['entities'] as $entities) {
            foreach ($entities as $entity) {
                $types[(string) $entity['type']] = true;
            }
        }

        $this -> assertTrue($types !== [], 'the corpus produces entities at all');

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
            json_encode(['ops' => [['insert' => self::CORPUS['german'] . "\n"]]]));

        $post_id = (int) mysqli_insert_id(DB::connection());

        EntityRanker::recompute();

        $stored = DB::row('
SELECT `detectedLanguage`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        $this -> assertSame('de', $stored ?-> detectedLanguage);
    }
}
