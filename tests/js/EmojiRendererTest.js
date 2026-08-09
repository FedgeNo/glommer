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
