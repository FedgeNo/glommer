import { TestCase } from './TestCase.js';
import { Strings } from '../../scripts/Strings.js';

/**
 * The browser half of the string table, held to the same fall-back behaviour
 * as Strings::for() in PHP (see tests/StringsTest.php).
 *
 * The claim that matters is the one that makes a thousand strings adoptable a
 * few at a time: a locale that has translated part of a sentence keeps English
 * for the parts it has not. Getting that wrong here and right on the server is
 * the worst version of it - the page reads correctly until the same sentence
 * is rebuilt by JavaScript, and only in a locale nobody has finished.
 */
export default {
    suite: 'Strings',
    tests: {
        'a class nobody translated is said in the English it was written with'() {
            Strings.useLocale({});

            const words = Strings.for('LoginPrompt', { before: '', link: 'Log in', after: ' to reply.' });

            TestCase.assertEquals('Log in', words.link);
            TestCase.assertEquals(' to reply.', words.after);
        },
        'a translated class is said in the translation'() {
            Strings.useLocale({ LoginPrompt: { before: 'Zum Antworten bitte ', link: 'anmelden', after: '.' } });

            const words = Strings.for('LoginPrompt', { before: '', link: 'Log in', after: ' to reply.' });

            TestCase.assertEquals('anmelden', words.link);
            TestCase.assertEquals('Zum Antworten bitte ', words.before);
        },
        'a piece of a sentence nobody translated is still said'() {
            Strings.useLocale({ LoginPrompt: { link: 'anmelden' } });

            const words = Strings.for('LoginPrompt', { before: '', link: 'Log in', after: ' to reply.' });

            TestCase.assertEquals('anmelden', words.link);
            TestCase.assertEquals(' to reply.', words.after, 'the piece nobody translated survives');
        },
        'a nested sentence falls back piece by piece, not entry by entry'() {
            Strings.useLocale({ Poll: { closes: { after: ' übrig' } } });

            const words = Strings.for('Poll', { closes: { before: 'closes in ', after: ' left' }, final: 'Final' });

            TestCase.assertEquals('closes in ', words.closes.before);
            TestCase.assertEquals(' übrig', words.closes.after);
            TestCase.assertEquals('Final', words.final, 'a sibling entry is untouched');
        },
        'a translated string replaces rather than merges into the English'() {
            Strings.useLocale({ Thing: { label: 'Etikett' } });

            TestCase.assertEquals('Etikett', Strings.for('Thing', { label: 'Label' }).label);
        },
        // The count is chosen by the language, the same as Strings::plural().
        'a count takes the form its language asks for'() {
            Strings.useLocale({}, 'pl');

            const forms = { one: '1 głos', few: '{count} głosy', many: '{count} głosów' };

            TestCase.assertEquals('1 głos', Strings.plural(forms, 1));
            TestCase.assertEquals('2 głosy', Strings.plural(forms, 2));
            TestCase.assertEquals('5 głosów', Strings.plural(forms, 5));
            TestCase.assertEquals('22 głosy', Strings.plural(forms, 22));
        },
        // A language saying there are no words for this here, which || threw
        // away in favour of another form while the server kept it.
        'a phrasing a language deliberately leaves empty is not swapped for another'() {
            Strings.useLocale({}, 'en');

            TestCase.assertEquals('', Strings.plural({ one: '', other: '{count} views' }, 1));
        },
        // .replace() with a string fills the first and prints the token at the
        // reader for the rest.
        'a phrasing that names the count twice fills it both times'() {
            Strings.useLocale({}, 'en');

            TestCase.assertEquals('3 of 3', Strings.plural({ other: '{count} of {count}' }, 3));
        },
    },
};
