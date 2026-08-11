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
 * The ones that need the environment stand down where it is not installed, so
 * this is honest on a machine that has never run bin/install.php. What can be
 * checked without it - the language rules, the sanitising - always runs.
 */
class TranslatorTest extends TestCase
{
    private function requireTranslator(): void
    {
        if (!Translator::isAvailable()) {
            throw new TestSkippedException('needs the translation environment - run bin/install.php');
        }
    }

    /** German to English, since that pairing is installed wherever any is. */
    private function intoEnglish(string $text): ?string
    {
        return Translator::translate($text, 'en', 'de');
    }

    // ---- What counts as a language, which needs no environment at all ----

    public function testATagIsReducedToTheLanguageItNames(): void
    {
        $this -> assertSame('pt', Translator::baseLanguage('pt-BR'));
        $this -> assertSame('en', Translator::baseLanguage('en-GB'));
        $this -> assertSame('zh', Translator::baseLanguage('zh-Hant-TW'));
        $this -> assertSame('de', Translator::baseLanguage('  DE  '));
    }

    /** Anything that is not a language never reaches a command line. */
    public function testWhatIsNotALanguageIsRefused(): void
    {
        foreach (['', '   ', 'x', 'toolong', '../../etc/passwd', 'de;rm -rf /', '-h', '4', 'de en'] as $rubbish) {
            $this -> assertNull(Translator::baseLanguage($rubbish), var_export($rubbish, true));
        }
    }

    public function testEveryWantedLanguageHasBothDirections(): void
    {
        $packages = Translator::wantedPackages();

        foreach (Translator::LANGUAGES as $language) {
            $this -> assertTrue(in_array('translate-en_' . $language, $packages, true), $language . ' out of English');
            $this -> assertTrue(in_array('translate-' . $language . '_en', $packages, true), $language . ' into English');
        }

        $this -> assertFalse(in_array(Translator::PIVOT, Translator::LANGUAGES, true), 'the pivot is not one of the pairs');
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
        $this -> assertNull(Translator::translate('Das Wetter ist schoen.', 'en', 'not-a-language'));
    }

    // ---- The text itself ----

    /**
     * The case that started this: a post beginning with a dash. It goes in on
     * stdin, so the command never sees it as an argument - but that is the
     * kind of thing that is true until somebody changes how the text is
     * passed, and then silently is not.
     */
    public function testAPostBeginningWithADashIsTextAndNotAFlag(): void
    {
        $this -> requireTranslator();

        $translated = $this -> intoEnglish('-- Das Wetter ist heute sehr schoen in Berlin.');

        $this -> assertNotNull($translated, 'a leading dash is not a reason to fail');
        $this -> assertTrue(str_contains(strtolower($translated), 'weather'), $translated);
    }

    public function testAPostThatLooksLikeTheCommandsOwnFlagsIsStillJustText(): void
    {
        $this -> requireTranslator();

        foreach (['--help', '--from-lang zz', '-h'] as $text) {
            $translated = Translator::translate($text, 'en', 'de');

            // Whatever it makes of these, it must not have acted on them: the
            // command printing its usage or dying would come back as nothing.
            $this -> assertNotNull($translated, var_export($text, true) . ' was acted on rather than translated');
        }
    }

    /** Shell metacharacters are text. Nothing is interpolated into a shell. */
    public function testShellMetacharactersAreJustCharacters(): void
    {
        $this -> requireTranslator();

        $translated = $this -> intoEnglish('Das Wetter; rm -rf /tmp/x && echo $(whoami) `id` | wc -l');

        $this -> assertNotNull($translated);
        $this -> assertFalse(str_contains($translated, 'root'), 'nothing was executed');
        $this -> assertFalse(str_contains($translated, 'uid='), 'nothing was executed');
    }

    /**
     * Several sentences, which is what found the memory cap - each one is a
     * batch, and the limit was reached by a post being long rather than by
     * anything being wrong.
     */
    public function testALongPostOfManySentencesSurvives(): void
    {
        $this -> requireTranslator();

        $text = trim(str_repeat('Das Wetter ist heute sehr schoen in Berlin. Die Leute sitzen draussen. ', 12));
        $translated = $this -> intoEnglish($text);

        $this -> assertNotNull($translated, 'a long post is not a reason to fail');
        $this -> assertTrue(mb_strlen($translated) > 100, 'the whole thing came back, not the first line');
    }

    /** A post at the cap is cut rather than refused, and cut on a character. */
    public function testAPostBeyondTheCapIsCutRatherThanRefused(): void
    {
        $this -> requireTranslator();

        $translated = $this -> intoEnglish(str_repeat('Schöne Grüße aus Berlin. ', 2000));

        $this -> assertNotNull($translated);
        $this -> assertTrue(mb_check_encoding($translated, 'UTF-8'), 'cut between characters, not through one');
    }

    public function testTextTheFarSideCannotDecodeIsCleanedRatherThanPassedOn(): void
    {
        $this -> requireTranslator();

        // A NUL ends a C string halfway through somebody's sentence; a stray
        // 0x80 is not UTF-8 and raises inside the command before it starts.
        $translated = $this -> intoEnglish("Das Wetter\0 ist heute \x80 sehr schoen in Berlin.");

        $this -> assertNotNull($translated, 'one bad byte is not a reason to hand back nothing');
        $this -> assertTrue(str_contains(strtolower($translated), 'weather'), $translated);
    }

    public function testEmojiAndAccentsComeBackIntact(): void
    {
        $this -> requireTranslator();

        $translated = $this -> intoEnglish('Schöne Grüße aus München 🎉 und auch aus Köln.');

        $this -> assertNotNull($translated);
        $this -> assertTrue(mb_check_encoding($translated, 'UTF-8'));
    }

    public function testLineBreaksDoNotBreakIt(): void
    {
        $this -> requireTranslator();

        $translated = $this -> intoEnglish("Das Wetter ist schoen.\n\nDie Leute sitzen draussen.\nEs ist warm.");

        $this -> assertNotNull($translated);
    }

    /** A post that is only punctuation has nothing to say in any language. */
    public function testAPostOfNothingButPunctuationDoesNotCrash(): void
    {
        $this -> requireTranslator();

        // Whatever comes back, it must be an answer rather than an exception -
        // null is a fine answer here.
        Translator::translate('... --- ... !!! ???', 'en', 'de');

        $this -> assertTrue(true, 'punctuation alone is handled rather than fatal');
    }
}
