<?php

declare(strict_types=1);

/** The request boundary for languages handed to a translator. */
class TranslationTargetTest extends TestCase
{
    /** Only languages this interface serves may become translator options. */
    public function testATranslationTargetMustBeAnOfferedLocale(): void
    {
        foreach (Strings::available() as $locale) {
            $this -> assertNotNull(PostTranslation::normalizeOfferedLanguage($locale), $locale);
        }

        $this -> assertSame('fr', PostTranslation::normalizeOfferedLanguage('fr-CA'));
        $this -> assertNull(PostTranslation::normalizeOfferedLanguage('zz'));
        $this -> assertNull(PostTranslation::normalizeOfferedLanguage('english please'));
    }
}
