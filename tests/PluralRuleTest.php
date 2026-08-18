<?php

declare(strict_types=1);

/** ICU plural categories exposed in the stable order locale files use. */
class PluralRuleTest extends TestCase
{
    public function testEveryLocaleHasAValidUniqueSetOfCategories(): void
    {
        foreach (Strings::available() as $locale) {
            $categories = PluralRule::categoriesFor($locale);

            $this -> assertTrue($categories !== [], $locale . ' has no plural categories');
            $this -> assertSame(array_values(array_unique($categories)), $categories, $locale . ' repeats a category');

            foreach ($categories as $category) {
                $this -> assertTrue(
                    in_array($category, PluralRule::CATEGORIES, true),
                    $locale . ' produced unknown category ' . $category
                );
            }
        }
    }

    public function testFilipinoUsesItsOwnICURules(): void
    {
        $this -> assertSame('one', PluralRule::categoryFor('fil', 1));
        $this -> assertSame('other', PluralRule::categoryFor('fil', 4));
        $this -> assertSame('one', PluralRule::categoryFor('fil', 8));
    }
}
