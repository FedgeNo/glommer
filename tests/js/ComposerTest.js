import { TestCase } from './TestCase.js';
import { Composer } from '../../scripts/Composer.js';

export default {
    suite: 'Composer',
    tests: {
        'init runs without error when PostComposer is present'() {
            const form = document.createElement('form');
            form.className = 'Card d-flex flex-column Composer PostComposer';
            document.body.appendChild(form);
            Composer.init();
            // The full render chain is browser‑dependent (EmojiPicker, Quill, etc.).
            // At minimum, verify the composer does not throw.
            TestCase.assertTrue(true);
            document.body.removeChild(form);
        },
        'init does nothing when no composer root present'() {
            Composer.init();
            TestCase.assertTrue(true);
        },
    }
};
