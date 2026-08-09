import { TestCase } from './TestCase.js';
import { EmojiRenderer } from '../../scripts/EmojiRenderer.js';

export default {
    suite: 'EmojiRenderer',
    tests: {
        'isEmojiOnly() returns false for plain text'() {
            const div = document.createElement('div');
            div.textContent = 'hello';
            TestCase.assertFalse(EmojiRenderer.isEmojiOnly(div));
        },
        'isEmojiOnly() returns true for pure emoji'() {
            const div = document.createElement('div');
            div.textContent = '😊🎉';
            TestCase.assertTrue(EmojiRenderer.isEmojiOnly(div));
        },
        'isEmojiOnly() returns false for mixed content'() {
            const div = document.createElement('div');
            div.textContent = 'hello 😊';
            TestCase.assertFalse(EmojiRenderer.isEmojiOnly(div));
        },
        'isEmojiOnly() counts a flag as emoji'() {
            const div = document.createElement('div');
            div.textContent = '🇺🇸 🇯🇵';
            TestCase.assertTrue(EmojiRenderer.isEmojiOnly(div));
        },
        // The pattern reaches "1️" and not the enclosing mark after it, so
        // stripping matches out left a stray character that read as text.
        'isEmojiOnly() counts a keycap as emoji'() {
            const div = document.createElement('div');
            div.textContent = '1️⃣';
            TestCase.assertTrue(EmojiRenderer.isEmojiOnly(div));
        },
        'isEmojiOnly() returns false for nothing at all'() {
            const div = document.createElement('div');
            div.textContent = '   ';
            TestCase.assertFalse(EmojiRenderer.isEmojiOnly(div));
        },
        'an emoji in what somebody wrote is wrapped'() {
            const body = document.createElement('div');
            body.className = 'PostContent';
            body.textContent = 'nice 😊';

            EmojiRenderer.render(body);

            TestCase.assertEquals(1, body.querySelectorAll('.emoji-text').length);
        },
        // The action bar's buttons are emoji, and so are display names, topics
        // and nav labels. Enlarging those was the whole bug: they are furniture
        // and are sized by their own rules.
        'init leaves emoji outside the content alone'() {
            const page = document.createElement('div');

            const button = document.createElement('button');
            button.className = 'PostLikeButton';
            button.textContent = '👍';

            const content = document.createElement('div');
            content.className = 'PostContent';
            content.textContent = 'nice 😊';

            page.appendWithSpace(button);
            page.appendWithSpace(content);
            document.body.appendWithSpace(page);

            EmojiRenderer.init();

            TestCase.assertEquals(0, button.querySelectorAll('.emoji-text').length, 'a button is not prose');
            TestCase.assertEquals(1, content.querySelectorAll('.emoji-text').length);

            document.body.removeChild(page);
        },
        // A flag is a PAIR of regional indicators with nothing joining them,
        // so anything matching one emoji at a time takes it as two and each
        // half renders as a big letter.
        'a flag stays one emoji'() {
            const body = document.createElement('div');
            body.className = 'PostContent';
            body.textContent = '🇺🇸';

            EmojiRenderer.render(body);

            const wrapped = body.querySelectorAll('.emoji-text');

            TestCase.assertEquals(1, wrapped.length);
            TestCase.assertEquals('🇺🇸', wrapped[0].textContent);
        },
        'two flags side by side stay two flags'() {
            const body = document.createElement('div');
            body.className = 'PostContent';
            body.textContent = '🇺🇸🇯🇵';

            EmojiRenderer.render(body);

            const wrapped = body.querySelectorAll('.emoji-text');

            TestCase.assertEquals(2, wrapped.length);
            TestCase.assertEquals('🇺🇸', wrapped[0].textContent);
            TestCase.assertEquals('🇯🇵', wrapped[1].textContent);
        },
        'a joined family is one emoji, not four'() {
            const body = document.createElement('div');
            body.className = 'PostContent';
            body.textContent = '👩‍👩‍👧‍👦';

            EmojiRenderer.render(body);

            TestCase.assertEquals(1, body.querySelectorAll('.emoji-text').length);
        },
        'a keycap keeps its digit'() {
            const body = document.createElement('div');
            body.className = 'PostContent';
            body.textContent = '1️⃣';

            EmojiRenderer.render(body);

            const wrapped = body.querySelectorAll('.emoji-text');

            TestCase.assertEquals(1, wrapped.length);
            TestCase.assertEquals('1️⃣', wrapped[0].textContent);
        },
        'a plain digit is not an emoji'() {
            const body = document.createElement('div');
            body.className = 'PostContent';
            body.textContent = 'I have 3 cats';

            EmojiRenderer.render(body);

            TestCase.assertEquals(0, body.querySelectorAll('.emoji-text').length);
            TestCase.assertEquals('I have 3 cats', body.textContent);
        },
        'the words around an emoji survive intact'() {
            const body = document.createElement('div');
            body.className = 'PostContent';
            body.textContent = 'off to 🇯🇵 tomorrow';

            EmojiRenderer.render(body);

            TestCase.assertEquals(1, body.querySelectorAll('.emoji-text').length);
            TestCase.assertEquals('off to 🇯🇵 tomorrow', body.textContent);
        },
        'rendering twice does not wrap what is already wrapped'() {
            const body = document.createElement('div');
            body.className = 'PostContent';
            body.textContent = 'nice 😊';

            EmojiRenderer.render(body);
            EmojiRenderer.render(body);

            TestCase.assertEquals(1, body.querySelectorAll('.emoji-text').length);
        },
    }
};
