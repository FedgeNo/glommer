import { TestCase } from './TestCase.js';
import { expand, expandInDOM } from '../../scripts/EmojiShortcode.js';

/**
 * The client half of shortcode expansion. It has to agree with the server's,
 * because the same post is rendered by both - server-side on first paint, and
 * client-side when it arrives by infinite scroll.
 */
export default {
    suite: 'EmojiShortcode',
    tests: {
        'a known shortcode becomes its emoji'() {
            TestCase.assertEquals('hello \u{1f604} world', expand('hello :smile: world'));
        },

        'an unknown name is left alone'() {
            // Which is what leaves room for a custom emoji: it travels as its
            // own name plus a per-post Emoji tag, and must survive to resolve.
            TestCase.assertEquals('a :blobcat: b', expand('a :blobcat: b'));
        },

        'a time is not an emoji'() {
            TestCase.assertEquals('meet at 12:30:45', expand('meet at 12:30:45'));
        },

        'adjacent shortcodes both expand'() {
            TestCase.assertEquals('\u{1f431}\u{1f436}', expand(':cat::dog:'));
        },

        'names are matched without regard to case'() {
            TestCase.assertEquals('\u{1f604}', expand(':SMILE:'));
        },

        'prose in a tree expands'() {
            const root = document.createElement('div');
            root.innerHTML = '<p>say :smile: now</p>';

            expandInDOM(root);

            TestCase.assertTrue(root.textContent.includes('\u{1f604}'));
        },

        'code in a tree is left alone'() {
            const root = document.createElement('div');
            root.innerHTML = '<p>see <code>:smile:</code></p><pre>x = :smile:</pre>';

            expandInDOM(root);

            TestCase.assertTrue(root.querySelector('code').textContent === ':smile:', 'inline code keeps its colons');
            TestCase.assertTrue(root.querySelector('pre').textContent === 'x = :smile:', 'a code block keeps its colons');
        },

        'a rendered formula is left alone'() {
            const root = document.createElement('div');
            root.innerHTML = '<span class="PostFormula">a :smile: b</span>';

            expandInDOM(root);

            TestCase.assertEquals('a :smile: b', root.textContent);
        },
    }
};
