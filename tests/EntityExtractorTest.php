<?php

declare(strict_types=1);

/**
 * What the trending extractor is willing to call a name.
 *
 * The model reads English and a relay carries every language, so short
 * function words come back tagged as organizations. They cannot be caught by
 * how often they appear - appearing everywhere, from many authors, is exactly
 * what trending is looking for, and on a live server the French "un" outranked
 * every real topic. Case is the thing that separates them.
 */
class EntityExtractorTest extends TestCase
{
    public function testAShortLowercaseWordIsNotAName(): void
    {
        foreach (['un', 'la', 'des', 'de', 'el', 'y'] as $word) {
            $this -> assertFalse(EntityExtractor::readsAsAName($word), $word . ' is a word, not a subject');
        }
    }

    /** The short names that are real are the reason this is about case, not length. */
    public function testShortCapitalisedNamesSurvive(): void
    {
        foreach (['US', 'AI', 'EU', 'UN', 'LA', 'BBC', 'iOS', 'Bob'] as $name) {
            $this -> assertTrue(EntityExtractor::readsAsAName($name), $name . ' is a name');
        }
    }

    /**
     * The same word opening a sentence is capitalised, which case alone cannot
     * tell from a name - this is what was left trending after the first pass
     * at it, spelled "Un".
     */
    public function testAFunctionWordIsRefusedInWhateverCaseItArrives(): void
    {
        foreach (['Un', 'La', 'Des', 'Der', 'Het', 'The', 'Une'] as $word) {
            $this -> assertFalse(EntityExtractor::readsAsAName($word), $word . ' opens a sentence, it is not the subject of one');
        }
    }

    /**
     * All-caps survives the word list, because that is how an initialism is
     * written and several of them spell a function word in some language.
     */
    public function testAnInitialismIsKeptEvenWhenItSpellsAWord(): void
    {
        foreach (['UN', 'IT', 'AS', 'IN', 'ON', 'A'] as $initialism) {
            $this -> assertTrue(EntityExtractor::readsAsAName($initialism), $initialism . ' is written as an initialism');
        }
    }

    /**
     * The test is only trustworthy on values too short to judge any other way,
     * so anything longer is kept whatever its case.
     */
    public function testLongerValuesAreLeftAlone(): void
    {
        $this -> assertTrue(EntityExtractor::readsAsAName('microsoft'));
        $this -> assertTrue(EntityExtractor::readsAsAName('des itinéraires alternatifs'));
    }

    /**
     * A script with no capitals cannot be written capitalised, so judging it
     * by case would throw away every short name in it.
     */
    public function testAScriptWithoutCapitalsIsNeverCaught(): void
    {
        $this -> assertTrue(EntityExtractor::readsAsAName('日本'), 'Japan is a place whatever the alphabet');
        $this -> assertTrue(EntityExtractor::readsAsAName('中国'));
    }

    /** Hashtags are somebody's own word for their own post, and stay lowercase. */
    public function testAHashtagIsNotJudgedThisWay(): void
    {
        $delta = (string) json_encode([['insert' => "a post about #ai and #un\n"]]);

        $entities = EntityExtractor::extractBatch([$delta])[0];

        $tags = array_values(array_map(
            static fn (array $entity): string => $entity['value'],
            array_filter($entities, static fn (array $entity): bool => $entity['type'] === 'hashtag')
        ));

        sort($tags);

        $this -> assertSame('ai,un', implode(',', $tags));
    }
}
