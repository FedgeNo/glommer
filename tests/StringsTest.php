<?php

declare(strict_types=1);

/**
 * Saying it in whichever language the reader asked for.
 *
 * The two claims worth holding are that a class contains no English of its own
 * and that a locale which has not been finished still renders a usable page.
 * The second is what makes over a thousand strings adoptable at all: without
 * it every intermediate state is a site with blanks in it, and the conversion
 * has to land as one enormous change or not at all.
 *
 * The runner calls test methods and nothing else, so each of these puts the
 * locale back itself - one left set would follow every later test in the run.
 */
class StringsTest extends TestCase
{
    private static function render(HTMLObject $object): string
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $object -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return (string) HTMLObject::currentDocument() -> saveHTML($element);
    }

    /** @return array{markup: string, text: string} */
    private static function renderIn(string $locale, HTMLObject $object): array
    {
        Strings::useLocale($locale);

        try {
            $markup = self::render($object);

            return ['markup' => $markup, 'text' => strip_tags($markup)];
        } finally {
            Strings::useLocale(null);
        }
    }

    public function testTheSourceLanguageReadsAsItAlwaysDid(): void
    {
        $this -> assertSame('Log in to reply.', self::renderIn('en', new LoginPrompt('reply'))['text']);
    }

    /**
     * The point of the whole shape: German puts the reason first and ends on
     * the verb, so the words move to the other side of the link and the link's
     * own label changes with them. LoginPrompt.php knows none of that.
     */
    public function testAnotherLanguageMovesTheWordsAndTheControlWithThem(): void
    {
        $rendered = self::renderIn('de', new LoginPrompt('reply'));

        $this -> assertSame('Zum Antworten bitte anmelden.', $rendered['text']);
        $this -> assertTrue(str_contains($rendered['markup'], '>anmelden</a>'), 'the control is translated too');
    }

    /**
     * Nothing the class holds itself leaks through. This is the check that a
     * class is finished - not "no sentences" but no words at all, the label on
     * the control included.
     */
    public function testTheClassContributesNoEnglishOfItsOwn(): void
    {
        $rendered = self::renderIn('de', new LoginPrompt('reply'));

        $this -> assertFalse(str_contains($rendered['text'], 'Log in'));
        $this -> assertFalse(str_contains($rendered['text'], 'reply'));
    }

    /** The link still goes where it went; only the words changed. */
    public function testTheLinkStillLeadsToTheSamePlace(): void
    {
        $rendered = self::renderIn('de', new LoginPrompt('reply'));

        $this -> assertTrue(str_contains($rendered['markup'], ServerURL::absolute('/login')));
    }

    /**
     * A locale that has not been finished falls back piece by piece, not entry
     * by entry - so translating one sentence of a class does not blank the
     * others, and one class can be converted per commit.
     */
    public function testWhatALocaleHasNotTranslatedIsStillSaidInEnglish(): void
    {
        $tables = new \ReflectionProperty(Strings::class, 'tables');
        $tables -> setAccessible(true);
        $locale = new \ReflectionProperty(Strings::class, 'locale');
        $locale -> setAccessible(true);

        $tables -> setValue(null, ['xx' => ['LoginPrompt' => ['reply' => ['link' => 'Ingresar']]]]);
        $locale -> setValue(null, 'xx');

        try {
            $sentence = Strings::for(LoginPrompt::class)['reply'];

            $this -> assertSame('Ingresar', $sentence['link'], 'the translated piece is used');
            $this -> assertSame(' to reply.', $sentence['after'], 'and the piece nobody translated is still said');
        } finally {
            $tables -> setValue(null, []);
            $locale -> setValue(null, null);
        }
    }

    public function testACountChoosesThePhrasing(): void
    {
        Strings::useLocale('en');

        try {
            $this -> assertSame('1 vote', Strings::plural(PollOptionVotes::class, 'votes', 1));
            $this -> assertSame('{count} votes', Strings::plural(PollOptionVotes::class, 'votes', 0));
            $this -> assertSame('{count} votes', Strings::plural(PollOptionVotes::class, 'votes', 7));
        } finally {
            Strings::useLocale(null);
        }
    }

    /**
     * The case English cannot demonstrate and the reason none of this is a
     * ternary in a class: Polish has three forms, and 2 takes a different one
     * from 5.
     */
    public function testALanguageWithThreeFormsGetsThreeForms(): void
    {
        $tables = new \ReflectionProperty(Strings::class, 'tables');
        $tables -> setAccessible(true);
        $locale = new \ReflectionProperty(Strings::class, 'locale');
        $locale -> setAccessible(true);

        $tables -> setValue(null, ['pl' => [
            // As a locale file carries it: cases tried in order, each naming a
            // category and what a count has to satisfy to take it.
            Strings::PLURAL_RULE => [
                ['category' => 'one', 'is' => [1]],
                ['category' => 'few', 'mod10' => [2, 3, 4], 'notMod100' => [12, 13, 14]],
                ['category' => 'many'],
            ],
            'PollOptionVotes' => ['votes' => [
                'one' => '1 głos',
                'few' => '{count} głosy',
                'many' => '{count} głosów',
            ]],
        ]]);
        $locale -> setValue(null, 'pl');

        try {
            $this -> assertSame('1 głos', Strings::plural(PollOptionVotes::class, 'votes', 1));
            $this -> assertSame('{count} głosy', Strings::plural(PollOptionVotes::class, 'votes', 2));
            $this -> assertSame('{count} głosów', Strings::plural(PollOptionVotes::class, 'votes', 5));
            $this -> assertSame('{count} głosów', Strings::plural(PollOptionVotes::class, 'votes', 12));
            $this -> assertSame('{count} głosy', Strings::plural(PollOptionVotes::class, 'votes', 22));
        } finally {
            $tables -> setValue(null, []);
            $locale -> setValue(null, null);
        }
    }

    /** A phrasing a locale has not written yet reads a little wrong rather than vanishing. */
    public function testAMissingFormFallsBackRatherThanDisappearing(): void
    {
        $tables = new \ReflectionProperty(Strings::class, 'tables');
        $tables -> setAccessible(true);
        $locale = new \ReflectionProperty(Strings::class, 'locale');
        $locale -> setAccessible(true);

        $tables -> setValue(null, ['xx' => [
            Strings::PLURAL_RULE => static fn (int $count): string => 'few',
            'PollOptionVotes' => ['votes' => ['other' => '{count} stemmer']],
        ]]);
        $locale -> setValue(null, 'xx');

        try {
            $this -> assertSame('{count} stemmer', Strings::plural(PollOptionVotes::class, 'votes', 3));
        } finally {
            $tables -> setValue(null, []);
            $locale -> setValue(null, null);
        }
    }

    /** English is the source, so it is always one of the languages on offer. */
    public function testTheSourceLanguageIsAlwaysAvailable(): void
    {
        $this -> assertTrue(in_array(Strings::SOURCE_LOCALE, Strings::available(), true));
    }

    /** A locale nobody has written words for is not one this can be asked to load. */
    public function testAnUnknownLocaleIsRefusedRatherThanRequired(): void
    {
        Strings::useLocale('../../etc/passwd');

        try {
            $this -> assertSame(Strings::SOURCE_LOCALE, Strings::locale());
        } finally {
            Strings::useLocale(null);
        }
    }
}
