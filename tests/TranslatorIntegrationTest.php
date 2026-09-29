<?php

declare(strict_types=1);

/** Installed translation uses the database-backed concurrency limit. */
class TranslatorIntegrationTest extends DatabaseTestCase
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
        // Exercise the installed programs even when Google would answer first.
        $translator = new class extends Translator {
            protected static function byGoogle(string $text, string $source, string $target): ?string
            {
                return null;
            }
        };

        return $translator::translate($text, 'en', 'de');
    }

    /** A leading dash must reach the translator as text, never as a flag. */
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
            $translated = $this -> intoEnglish($text);

            // Usage output or a command failure would return nothing.
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

    /** Several sentences exercise the installed translator's memory limit. */
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

        // A NUL ends a C string; a stray 0x80 is invalid UTF-8.
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

        // Null is a fine answer; an exception is not.
        $this -> intoEnglish('... --- ... !!! ???');

        $this -> assertTrue(true, 'punctuation alone is handled rather than fatal');
    }
}
