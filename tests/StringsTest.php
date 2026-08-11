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
