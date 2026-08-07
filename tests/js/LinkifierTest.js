import { TestCase } from './TestCase.js';
import { Linkifier } from '../../scripts/Linkifier.js';

/**
 * The browser half of the tokenizer, held to the same answers as
 * tests/LinkifierTest.php. The two renderers build the same DOM from the same
 * text, so a disagreement here is a post that reads one way when the server
 * sends it and another when the page rebuilds it.
 *
 * The cases below are the ones that only a byte-versus-code-point difference
 * could break: PHP scans bytes and slices by byte offset, this scans code
 * points and slices by UTF-16 offset.
 */
export default {
    suite: 'Linkifier',
    tests: {
        'an accented tag resolves the same as it does on the server'() {
            const segments = Linkifier.tokenize('un #café');

            TestCase.assertEquals('hashtag', segments[1].type);
            TestCase.assertEquals('café', segments[1].tag);
        },
        'a CJK tag is a tag'() {
            const segments = Linkifier.tokenize('read #日本語 here');

            TestCase.assertEquals('hashtag', segments[1].type);
            TestCase.assertEquals('日本語', segments[1].tag);
        },
        'a tag is lowercased beyond ASCII too'() {
            TestCase.assertEquals('café', Linkifier.tokenize('#CAFÉ')[0].tag);
        },
        'the text around a non-ASCII tag survives intact'() {
            const segments = Linkifier.tokenize('vor #Ünicode nach');

            TestCase.assertEquals('vor ', segments[0].text);
            TestCase.assertEquals('#Ünicode', segments[1].text);
            TestCase.assertEquals('ünicode', segments[1].tag);
            TestCase.assertEquals(' nach', segments[2].text);
        },
        'the length cap counts characters, not the units they take'() {
            TestCase.assertTrue(Linkifier.isTagSlug('é'.repeat(Linkifier.MAX_TAG_LENGTH)));
            TestCase.assertFalse(Linkifier.isTagSlug('é'.repeat(Linkifier.MAX_TAG_LENGTH + 1)));
        },
        'a number is still not a tag'() {
            for (const segment of Linkifier.tokenize('happy #2024 everyone')) {
                TestCase.assertFalse(segment.type === 'hashtag');
            }
        },
        'a tag still needs a boundary in front of it'() {
            for (const segment of Linkifier.tokenize('a#b and ##b')) {
                TestCase.assertFalse(segment.type === 'hashtag');
            }
        },
        'an accented word before a hash does not start a tag'() {
            for (const segment of Linkifier.tokenize('café#nope')) {
                TestCase.assertFalse(segment.type === 'hashtag');
            }
        },
    }
};
