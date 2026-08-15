<?php

declare(strict_types=1);

/**
 * The judgement that stands between a machine translation and the interface.
 *
 * None of this needs a model or a package installed: what is tested here is
 * what the tool does with an answer once it has one - which answers it refuses,
 * what it repairs, and what it leaves alone. Every case below is one a real
 * answer got wrong.
 */
class StringTranslatorTest extends TestCase
{
    /**
     * A locale file holds more than language: the clock is a number, and
     * handing a number to a model is how a locale ends up telling the time in
     * something nobody wrote.
     */
    public function testOnlyActualStringsAreOfferedForTranslation(): void
    {
        $flat = StringTranslator::flatten([
            'DateFormat' => ['clock' => 12, 'am' => 'AM'],
            'LoginForm' => ['submit' => 'Log In', 'blank' => '   '],
        ]);

        $this -> assertSame(['DateFormat.am' => 'AM', 'LoginForm.submit' => 'Log In'], $flat);
    }

    /** The written order of en.json, not the order the answers came back in. */
    public function testTheFileKeepsTheOrderTheSourceWasWrittenIn(): void
    {
        $merged = StringTranslator::merge(
            ['A' => ['first' => 'One', 'second' => 'Two'], 'B' => ['third' => 'Three']],
            ['B.third' => 'Drei', 'A.second' => 'Zwei', 'A.first' => 'Eins']
        );

        $this -> assertSame(['A' => ['first' => 'Eins', 'second' => 'Zwei'], 'B' => ['third' => 'Drei']], $merged);
    }

    /** A key with no translation is left out rather than written empty. */
    public function testAKeyThatDidNotTranslateIsLeftOutOfTheFile(): void
    {
        $merged = StringTranslator::merge(
            ['A' => ['kept' => 'One', 'lost' => 'Two'], 'B' => ['alone' => 'Three']],
            ['A.kept' => 'Eins']
        );

        $this -> assertSame(['A' => ['kept' => 'Eins']], $merged);
    }

    /**
     * Polish counts in one/few/many where English counts in one/other, and a
     * file built from English's shape alone would delete the two forms Polish
     * actually needs - every run, silently, leaving it counting in the wrong
     * grammar.
     */
    public function testAFormThisLanguageHasAndEnglishLacksSurvives(): void
    {
        $merged = StringTranslator::merge(
            ['RelativeTime' => ['minutes' => ['one' => '{count}m ago', 'other' => '{count}m ago']]],
            [
                'RelativeTime.minutes.one' => '{count} min temu',
                'RelativeTime.minutes.other' => '{count} min temu',
                'RelativeTime.minutes.few' => '{count} min temu',
                'RelativeTime.minutes.many' => '{count} min temu',
            ]
        );

        $this -> assertSame(
            ['one', 'other', 'few', 'many'],
            array_keys($merged['RelativeTime']['minutes']),
            'the forms English does not share were dropped'
        );
    }

    /**
     * English writes "See {link}" and Japanese "{link}を見る", so the fragment
     * English leaves empty is the one Japanese fills in. An empty source
     * string is not a translatable string, so it has to survive on the shape
     * of the branch rather than on English having something to say there.
     */
    public function testAFragmentEnglishLeavesEmptySurvivesWhereALanguageFillsIt(): void
    {
        $merged = StringTranslator::merge(
            ['MoreLocationsLink' => ['moreLocations' => ['before' => 'See ', 'link' => 'more locations', 'after' => '']]],
            [
                'MoreLocationsLink.moreLocations.link' => '他の地域',
                'MoreLocationsLink.moreLocations.after' => 'を見る',
            ]
        );

        $this -> assertSame('を見る', $merged['MoreLocationsLink']['moreLocations']['after'] ?? null);
    }

    /**
     * A locale's blank is a decision and has to survive being read back;
     * the source's is just nothing to translate.
     */
    public function testALocalesBlankIsReadBackAndTheSourcesIsNot(): void
    {
        $table = ['MoreLocationsLink' => ['moreLocations' => ['before' => '', 'link' => '他の地域']]];

        $this -> assertSame(
            ['MoreLocationsLink.moreLocations.link' => '他の地域'],
            StringTranslator::flatten($table)
        );

        $this -> assertSame(
            ['MoreLocationsLink.moreLocations.before' => '', 'MoreLocationsLink.moreLocations.link' => '他の地域'],
            StringTranslator::flatten($table, '', true)
        );
    }

    /**
     * Written back rather than dropped: a language that says nothing where
     * English says "See " would otherwise lose the blank each run and be
     * asked to fill it again.
     */
    public function testADeliberateBlankIsWrittenBackIntoTheFile(): void
    {
        $merged = StringTranslator::merge(
            ['MoreLocationsLink' => ['moreLocations' => ['before' => 'See ', 'link' => 'more locations']]],
            ['MoreLocationsLink.moreLocations.before' => '', 'MoreLocationsLink.moreLocations.link' => '他の地域']
        );

        $this -> assertSame(
            ['before' => '', 'link' => '他の地域'],
            $merged['MoreLocationsLink']['moreLocations']
        );
    }

    /** A key nobody has translated is absent, and stays out of the file. */
    public function testAKeyNobodyHasTranslatedStaysOut(): void
    {
        $merged = StringTranslator::merge(
            ['A' => ['one' => 'One', 'two' => 'Two']],
            ['A.one' => 'Eins']
        );

        $this -> assertSame(['A' => ['one' => 'Eins']], $merged);
    }

    /**
     * German tells the time on a 24-hour clock, which is not a sentence
     * anybody translated. Rebuilt from English's shape and the strings that
     * came back, a locale lost it - and read 3:04 for a quarter past three in
     * the afternoon.
     */
    public function testWhatIsTheLocalesOwnRatherThanLanguageSurvives(): void
    {
        $merged = StringTranslator::merge(
            ['DateFormat' => ['clock' => 12, 'am' => 'AM']],
            ['DateFormat.am' => 'AM'],
            ['DateFormat' => ['clock' => 24, 'am' => 'AM']]
        );

        $this -> assertSame(24, $merged['DateFormat']['clock'], 'the clock was dropped');
    }

    /** A locale with no clock of its own gets the source's. */
    public function testALocaleWithoutOneOfItsOwnFallsBackToTheSource(): void
    {
        $merged = StringTranslator::merge(['DateFormat' => ['clock' => 12]], [], []);

        $this -> assertSame(12, $merged['DateFormat']['clock']);
    }

    /** A class English has dropped is walked by nobody and goes with it. */
    public function testAClassEnglishNoLongerHasIsNotCarriedForward(): void
    {
        $merged = StringTranslator::merge(
            ['Kept' => ['word' => 'One']],
            ['Kept.word' => 'Eins', 'Gone.word' => 'Zwei', 'Gone.extra' => 'Drei']
        );

        $this -> assertSame(['Kept' => ['word' => 'Eins']], $merged);
    }

    /**
     * A model reads "{count}" as a word and translates what is inside the
     * braces, taking the rest of the sentence with it.
     */
    public function testPlaceholdersGoOverAsSomethingAModelLeavesAlone(): void
    {
        [$masked, $sentinels] = StringTranslator::mask('{count} of {total} views');

        $this -> assertSame('X1X of X2X views', $masked);
        $this -> assertSame('{count} of {total} views', StringTranslator::unmask($masked, $sentinels));
    }

    /** The same placeholder twice is one sentinel, used twice. */
    public function testAPlaceholderUsedTwiceKeepsOneSentinel(): void
    {
        [$masked, $sentinels] = StringTranslator::mask('{name} replied to {name}');

        $this -> assertSame('X1X replied to X1X', $masked);
        $this -> assertSame('{name} replied to {name}', StringTranslator::unmask($masked, $sentinels));
    }

    /** A sentence rendered with a hole in it is worse than one in English. */
    public function testAnAnswerThatLostAPlaceholderIsRefused(): void
    {
        $this -> assertFalse(StringTranslator::keepsPlaceholders('{count} views', 'Vistas'));
    }

    /** Doubled, it prints the number twice. */
    public function testAnAnswerThatGainedAPlaceholderIsRefused(): void
    {
        $this -> assertFalse(StringTranslator::keepsPlaceholders('{count} views', '{count} {count} vistas'));
    }

    /** Left in, it shows the reader the scaffolding. */
    public function testAnAnswerStillCarryingASentinelIsRefused(): void
    {
        $this -> assertFalse(StringTranslator::keepsPlaceholders('{count} views', '{count} vistas X2X'));
    }

    public function testAnAnswerThatKeptItsPlaceholdersIsAccepted(): void
    {
        $this -> assertTrue(StringTranslator::keepsPlaceholders('{count} of {total}', '{count} de {total}'));
    }

    /**
     * The stutter a model falls into when it is handed a label with no
     * sentence around it.
     */
    public function testAnAnswerThatRepeatsItselfIsRefused(): void
    {
        $this -> assertTrue(StringTranslator::isDegenerate('Log In', 'Log In Log In Log In Log In'));
    }

    /** Two words is enough of a stutter, and never trips the length test. */
    public function testATwoWordStutterIsRefused(): void
    {
        $this -> assertTrue(StringTranslator::isDegenerate('Password', 'Password Password'));
    }

    /** Ordinary prose repeats words without being broken. */
    public function testProseThatRepeatsAWordIsLeftAlone(): void
    {
        $this -> assertFalse(StringTranslator::isDegenerate(
            'You are responsible for what you post here.',
            'Vous êtes responsable de ce que vous publiez ici.'
        ));
    }

    /** A short label whose answer runs away is refused on length alone. */
    public function testAnAnswerFarLongerThanItsSourceIsRefused(): void
    {
        $this -> assertTrue(StringTranslator::isDegenerate('Yes', str_repeat('a', 40)));
    }

    /**
     * "Vistas 5" reads as though the 5 were part of the label rather than the
     * count it is.
     */
    public function testTheCountedNumberGoesBackWhereEnglishHadIt(): void
    {
        $this -> assertSame('{count} vistas', StringTranslator::numberFirst('{count} views', 'vistas {count}'));
    }

    /** An answer that already leads with it is left alone. */
    public function testAnAnswerAlreadyLeadingWithTheNumberIsLeftAlone(): void
    {
        $this -> assertSame('{count} vistas', StringTranslator::numberFirst('{count} views', '{count} vistas'));
    }

    /** English that does not lead with the number has no opinion to enforce. */
    public function testASentenceThatNeverLedWithTheNumberIsLeftAlone(): void
    {
        $this -> assertSame(
            'publicado por {name}',
            StringTranslator::numberFirst('posted by {name}', 'publicado por {name}')
        );
    }

    /** A button with a full stop on it reads as prose. */
    public function testAFullStopTheLabelNeverHadIsTakenOff(): void
    {
        $this -> assertSame('Ja', StringTranslator::punctuatedAsSource('Yes', 'Ja.'));
    }

    /** A real sentence keeps the ending it was written with. */
    public function testASentenceKeepsItsOwnPunctuation(): void
    {
        $this -> assertSame(
            'Sie sind verantwortlich.',
            StringTranslator::punctuatedAsSource('You are responsible.', 'Sie sind verantwortlich.')
        );
    }

    /** Nothing has been translated before, so everything is stale. */
    public function testALocaleWithNothingInItNeedsAllOfIt(): void
    {
        $stale = StringTranslator::stale(['A.one' => 'One', 'A.two' => 'Two'], [], []);

        $this -> assertSame(['A.one' => 'One', 'A.two' => 'Two'], $stale);
    }

    /** A rerun with nothing edited asks for nothing. */
    public function testAStringTranslatedFromTheEnglishItStillSaysIsNotStale(): void
    {
        $stale = StringTranslator::stale(
            ['A.one' => 'One'],
            ['A.one' => 'Eins'],
            ['A.one' => StringTranslator::fingerprint('One')]
        );

        $this -> assertSame([], $stale);
    }

    /** Editing the English is what makes every locale's copy of it stale. */
    public function testEditingTheEnglishMakesTheTranslationStale(): void
    {
        $stale = StringTranslator::stale(
            ['A.one' => 'Just one'],
            ['A.one' => 'Eins'],
            ['A.one' => StringTranslator::fingerprint('One')]
        );

        $this -> assertSame(['A.one' => 'Just one'], $stale);
    }

    /**
     * The prompt is the one string shown to somebody who cannot read the page,
     * so each locale's copy has to offer its own language rather than English.
     */
    public function testTheLanguagePromptOffersTheLanguageItIsWrittenIn(): void
    {
        $this -> assertSame(
            'Would you like to view the site in Spanish?',
            StringTranslator::sourceFor('es', 'LanguagePrompt.question', 'Would you like to view the site in English?')
        );
    }

    /** Every other string is handed over as written. */
    public function testAnOrdinaryStringIsHandedOverUntouched(): void
    {
        $this -> assertSame(
            'Log In',
            StringTranslator::sourceFor('es', 'LoginForm.submit', 'Log In')
        );
    }

    /**
     * A month name is a fact about a locale rather than a translation, and it
     * is the class of string a model most reliably ruins.
     */
    public function testMonthNamesComeFromTheCalendarRatherThanAModel(): void
    {
        $this -> assertSame('enero', StringTranslator::fromCalendar('es', 'DateFormat.months.1'));
        $this -> assertSame('Januar', StringTranslator::fromCalendar('de', 'DateFormat.months.1'));
    }

    /**
     * The order of a date's parts belongs to the locale, not to a translator:
     * translated instead of read, every language renders dates in US order.
     */
    public function testTheDatePatternIsTheLocalesOwnOrder(): void
    {
        $japanese = StringTranslator::fromCalendar('ja', 'DateFormat.long');

        $this -> assertNotNull($japanese);
        $this -> assertTrue(
            mb_strpos($japanese, '{year}') < mb_strpos($japanese, '{day}'),
            'Japanese writes the year before the day: ' . $japanese
        );
    }

    /** Anything that is language rather than calendar data is left to the model. */
    public function testAnOrdinaryKeyHasNoCalendarAnswer(): void
    {
        $this -> assertNull(StringTranslator::fromCalendar('es', 'LoginForm.submit'));
    }

    /**
     * On a server without intl there is no calendar answer to write, and the
     * model must still not be asked - which is a different question from
     * whether ICU can answer, and the reason the two are asked separately.
     */
    public function testCalendarKeysAreKnownAsICUsWhetherOrNotICUIsHere(): void
    {
        $this -> assertTrue(StringTranslator::isCalendar('DateFormat.months.1'));
        $this -> assertTrue(StringTranslator::isCalendar('DateFormat.shortMonths.12'));
        $this -> assertTrue(StringTranslator::isCalendar('DateFormat.am'));
        $this -> assertTrue(StringTranslator::isCalendar('DateFormat.long'));
    }

    /** The rest of a locale's dates are wording, and a model may have them. */
    public function testAKeyThatIsWordingRatherThanCalendarDataIsNotICUs(): void
    {
        $this -> assertFalse(StringTranslator::isCalendar('DateFormat.dateAndTime'));
        $this -> assertFalse(StringTranslator::isCalendar('LoginForm.submit'));
    }
}
