<?php

declare(strict_types=1);

/** Locale source schemas and metadata derived without translating prose. */
class StringTranslatorTest extends TestCase
{
    public function testFlattenCanPreserveAnIntentionalEmptyFragment(): void
    {
        $table = ['Sentence' => ['before' => '', 'after' => 'words']];

        $this -> assertSame(['Sentence.after' => 'words'], StringTranslator::flatten($table));
        $this -> assertSame(
            ['Sentence.before' => '', 'Sentence.after' => 'words'],
            StringTranslator::flatten($table, '', true)
        );
    }

    public function testExpansionUsesOtherForCategoriesEnglishDoesNotName(): void
    {
        $source = [
            'Counter.label.one' => 'one thing',
            'Counter.label.other' => '{count} things',
        ];
        $expanded = StringTranslator::expanded($source, 'ar');

        foreach (PluralRule::categoriesFor('ar') as $category) {
            $this -> assertTrue(isset($expanded['Counter.label.' . $category]), 'Arabic is missing ' . $category);
        }

        $this -> assertSame('{count} things', $expanded['Counter.label.few']);
        $this -> assertSame('{count} things', $expanded['Counter.label.many']);
    }

    public function testFingerprintTracksExactSourceWording(): void
    {
        $this -> assertSame(sha1('Current English'), StringTranslator::fingerprint('Current English'));
        $this -> assertFalse(
            StringTranslator::fingerprint('Current English') === StringTranslator::fingerprint('Current English ')
        );
    }
}
