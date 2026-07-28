import { TestCase } from './TestCase.js';
import { EmojiRenderer } from '../../EmojiRenderer.js';

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
    }
};
