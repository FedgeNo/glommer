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

        'a custom emoji renders as an image'() {
            const root = document.createElement('div');
            root.appendChild(document.createTextNode('look :blobcat: here'));

            expandInDOM(root, { blobcat: 'https://remote.invalid/blobcat.png' });

            const image = root.querySelector('img.CustomEmoji');

            TestCase.assertNotNull(image);
            TestCase.assertEquals('https://remote.invalid/blobcat.png', image.getAttribute('src'));
            TestCase.assertEquals(':blobcat:', image.alt);
            TestCase.assertTrue(root.textContent.includes('look '), 'the surrounding text survives');
        },

        'a custom name beats the unicode table'() {
            // A tag is the sending server saying what a shortcode means in THIS
            // post - a more specific claim than a table everyone shares.
            const root = document.createElement('div');
            root.appendChild(document.createTextNode(':smile:'));

            expandInDOM(root, { smile: 'https://remote.invalid/theirs.png' });

            TestCase.assertNotNull(root.querySelector('img.CustomEmoji'));
            TestCase.assertFalse(root.textContent.includes('\u{1f604}'));
        },

        'a custom emoji in code is left alone'() {
            const root = document.createElement('div');
            root.innerHTML = '<pre>x = :blobcat:</pre>';

            expandInDOM(root, { blobcat: 'https://remote.invalid/blobcat.png' });

            TestCase.assertEquals('x = :blobcat:', root.querySelector('pre').textContent);
        },

        'a rendered formula is left alone'() {
            const root = document.createElement('div');
            root.innerHTML = '<span class="PostFormula">a :smile: b</span>';

            expandInDOM(root);

            TestCase.assertEquals('a :smile: b', root.textContent);
        },
    }
};
