<?php

declare(strict_types=1);

/**
 * Translating a post, and everything a post can be.
 *
 * The failures worth catching here are the ones that look like something else.
 * A post beginning with a dash reads as a flag to any program taking it on a
 * command line. A post one sentence longer than the last died inside the
 * translator with "mkl_malloc: failed to allocate memory", which reads as a
 * broken machine and was an address-space cap set near what a translation
 * appears to use rather than what it reserves.
 *
 * Language rules and input checks run without the installed translator or a
 * database. Translation integration cases live in TranslatorIntegrationTest.
 */
class TranslatorTest extends TestCase
{
    // ---- What counts as a language, which needs no environment at all ----

    public function testATagIsReducedToTheLanguageItNames(): void
    {
        $this -> assertSame('pb', Translator::baseLanguage('pt-BR'));
        $this -> assertSame('pb', Translator::baseLanguage('pb'));
        $this -> assertSame('en', Translator::baseLanguage('en-GB'));
        $this -> assertSame('zt', Translator::baseLanguage('zh-Hant-TW'));
        $this -> assertSame('zt', Translator::baseLanguage('zh-TW'));
        $this -> assertSame('tl', Translator::baseLanguage('fil-PH'));
        $this -> assertSame('tl', Translator::baseLanguage('tl'));
        $this -> assertSame('de', Translator::baseLanguage('  DE  '));
    }

    /** Anything that is not a language never reaches a command line. */
    public function testWhatIsNotALanguageIsRefused(): void
    {
        foreach (['', '   ', 'x', 'toolong', '../../etc/passwd', 'de;rm -rf /', '-h', '4', 'de en'] as $rubbish) {
            $this -> assertNull(Translator::baseLanguage($rubbish), var_export($rubbish, true));
        }
    }

    /**
     * What the installer downloads is the difference between two argospm
     * listings, which are printed differently - so reading one of them as the
     * other would have it download every package it already holds.
     */
    public function testBothArgosListingsAreReadAsPackageNames(): void
    {
        $search = Translator::packagesIn("translate-sq_en: sq -> en\ntranslate-en_zh: en -> zh\n");
        $list = Translator::packagesIn("translate-sq_en\ntranslate-en_zh\n");
        $versioned = Translator::packagesIn("translate-sq_en-1_9\ntranslate-en_zh-1_1\n");

        $this -> assertSame(['translate-sq_en', 'translate-en_zh'], $search);
        $this -> assertSame($search, $list, 'the same packages, however they were listed');
        $this -> assertSame($search, $versioned, 'a version on the end is the same package, not a missing one');
    }

    /** Standard output carries whatever else the command had to say. */
    public function testWhatIsNotAPackageNameIsNotTakenForOne(): void
    {
        $listing = "Downloading index...\ntranslate-en_es: en -> es\n\nrm -rf /: nice try\n";

        $this -> assertSame(['translate-en_es'], Translator::packagesIn($listing));
    }

    /**
     * Both naming shapes Argos has published, because a server added to over
     * time holds a mix of them and a language read from neither is a language
     * the installation quietly stops offering.
     */
    public function testAPackageDirectoryNamesItsTwoLanguages(): void
    {
        $languages = Translator::languagesIn(['en_es', 'translate-de_en-1_3', 'stanza_resources', '__pycache__', '.']);

        sort($languages);

        $this -> assertSame(['de', 'en', 'es'], $languages);
    }

    // ---- Requests that should be turned away before anything is run ----

    public function testTranslatingIntoTheLanguageItIsAlreadyInIsNotAttempted(): void
    {
        $this -> assertNull(Translator::translate('Das Wetter ist schoen.', 'de', 'de'));
        $this -> assertNull(Translator::translate('Das Wetter ist schoen.', 'de-AT', 'de-DE'), 'same language, different places');
    }

    public function testNothingToTranslateIsNotTranslated(): void
    {
        $this -> assertNull(Translator::translate('', 'en', 'de'));
        $this -> assertNull(Translator::translate("   \n\t  ", 'en', 'de'));
    }

    public function testAnUnknownSourceIsRefused(): void
    {
        $this -> assertNull(Translator::translate('Das Wetter ist schoen.', 'en', null));
        $this -> assertNull(Translator::translate('Das Wetter ist schoen.', 'en', 'invalid'));
    }

    /**
     * A language with no package is turned away here rather than by the
     * command. "not-a-language" reduces to "not", which is three letters and
     * passes for a tag - and would otherwise cost a slot and five seconds to
     * learn what this already knows.
     */
    public function testOnlyLanguagesThisInstallationHasAPackageForAreAttempted(): void
    {
        foreach (Translator::installedLanguages() as $language) {
            $this -> assertTrue(Translator::isSupported($language), $language);
            $this -> assertTrue(Translator::isSupported($language . '-BR'), 'a place does not make it another language');
        }

        foreach (['not-a-language', 'xyz', null, ''] as $unsupported) {
            $this -> assertFalse(Translator::isSupported($unsupported), var_export($unsupported, true));
        }
    }

    /**
     * The six site locales SMaLL-100 was never trained to answer to, so
     * Argos has to cover them - either they collapse to a generic code the
     * model cannot ask for specifically (Norwegian, Portuguese, Chinese), or
     * they are not in M2M-100 at all (Esperanto, Basque, Kyrgyz).
     */
    public function testSMaLL100DoesNotClaimTheSixLanguagesArgosCovers(): void
    {
        foreach (['eo', 'eu', 'ky', 'nb', 'pt-BR', 'zh-Hant'] as $gap) {
            $this -> assertFalse(Translator::isSmall100Supported($gap), $gap);
        }

        foreach (['de', 'es', 'fr', 'ja', 'zh', 'pt'] as $covered) {
            $this -> assertTrue(Translator::isSmall100Supported($covered), $covered);
        }

        $this -> assertFalse(Translator::isSmall100Supported(null));
        $this -> assertFalse(Translator::isSmall100Supported('not-a-language'));
    }

    /**
     * Found live on prod: SMaLL-100 can loop on longer or unusual input
     * rather than fail outright, and a loop reads as a translation, not an
     * error - "You can find the best way you can find the best way..." came
     * back with exit 0. Caught on the words, not the exit status.
     */
    private function isSmall100Repetitive(string $text): bool
    {
        $method = new \ReflectionMethod(Translator::class, 'isRepetitive');
        $method -> setAccessible(true);

        return (bool) $method -> invoke(null, $text);
    }

    public function testALoopingTranslationIsRecognisedAsOne(): void
    {
        $this -> assertTrue($this -> isSmall100Repetitive(
            'You can find the best way you can find the best way you can find the best way you can find the best way'
        ));
    }

    public function testOrdinaryProseIsNotMistakenForALoop(): void
    {
        $this -> assertFalse($this -> isSmall100Repetitive(
            'The weather report predicts rain for tomorrow, but it is expected to be sunny again on the weekend.'
        ));

        // Short answers repeat a handful of words by nature ("thank you very
        // much, thank you") without being stuck - the length guard is what
        // keeps these from false-positiving, not the phrase count alone.
        $this -> assertFalse($this -> isSmall100Repetitive('Thank you very much, thank you'));
    }
}
