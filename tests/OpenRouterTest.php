<?php

declare(strict_types=1);

/**
 * The one thing every AI feature here depends on being handled below it: the
 * router picks whatever model is free, and some of what it picks classifies
 * the message instead of doing as it was asked. Left alone that reaches the
 * page - a translation nobody can read, a topic summary sitting on a tag.
 *
 * Recognised by the label a verdict opens with, since the verdict itself is
 * whatever the model felt like saying. The model call is not exercised: no
 * key is configured under test, and a feature that spends tokens must degrade
 * to "no answer" rather than to an error.
 */
class OpenRouterTest extends TestCase
{
    private const NEWLINE = "\n";

    /**
     * Each of these has actually come back from the free router in place of
     * an answer.
     */
    public function testAVerdictOnItsOwnLeavesNothing(): void
    {
        $this -> assertSame('', OpenRouter::withoutVerdict('User Safety: safe'));
        $this -> assertSame('', OpenRouter::withoutVerdict('safety: SAFE'));
        $this -> assertSame(
            '',
            OpenRouter::withoutVerdict('Safety Categories: Violence, Needs Caution, High Risk Gov Decision Making')
        );
    }

    /**
     * The label is the stable half. What a classifier decides changes with
     * every message and every model, so nothing after the colon is compared -
     * only the name in front of it, which that classifier will keep using.
     */
    public function testWhateverTheVerdictSaysAfterTheLabelIsIrrelevant(): void
    {
        foreach (['safe', 'unsafe', 'S1, S9', '', 'anything at all here'] as $verdict) {
            $this -> assertSame('', OpenRouter::withoutVerdict('User Safety: ' . $verdict), $verdict);
        }

        // Spaced or cased differently, still the same label.
        $this -> assertSame('', OpenRouter::withoutVerdict('user safety : safe'));
        $this -> assertSame('', OpenRouter::withoutVerdict('USER SAFETY: safe'));
    }

    /** A longer name that merely starts the same way is a different thing. */
    public function testALabelThatOnlyBeginsTheSameIsNotAVerdict(): void
    {
        $answer = 'User Safety Notes: written up in full below.';

        $this -> assertSame($answer, OpenRouter::withoutVerdict($answer));
    }

    public function testAVerdictBesideARealAnswerIsTakenOffIt(): void
    {
        $this -> assertSame(
            'hello everyone',
            OpenRouter::withoutVerdict('User Safety: safe' . self::NEWLINE . self::NEWLINE . 'hello everyone')
        );

        $this -> assertSame(
            'hello everyone',
            OpenRouter::withoutVerdict('hello everyone' . self::NEWLINE . 'Safety Categories: None')
        );
    }

    /** Ordinary words are left exactly as they came, lines and all. */
    public function testAnAnswerIsNotTrimmedOfItsOwnLines(): void
    {
        $answer = 'first line' . self::NEWLINE . 'second' . self::NEWLINE . self::NEWLINE . 'a paragraph on';

        $this -> assertSame($answer, OpenRouter::withoutVerdict($answer));
    }

    /**
     * The label has to open the line. A sentence about safety is somebody
     * writing about safety, not a model refusing to answer.
     */
    public function testWordsThatMerelyMentionSafetyAreNotAVerdict(): void
    {
        $answer = 'The user safety: rules were discussed at length.';

        $this -> assertSame($answer, OpenRouter::withoutVerdict($answer));
    }

    public function testWithoutAKeyThereIsNoAnswerAtAll(): void
    {
        Settings::set(OpenRouter::API_KEY_SETTING, '');

        $this -> assertFalse(OpenRouter::isEnabled());
        $this -> assertNull(OpenRouter::chat([['role' => 'user', 'content' => 'hello']]));
    }
}
