import { TestCase } from './TestCase.js';
import { Composer } from '../../scripts/Composer.js';

/**
 * A post carries a link, attached media, or a poll - one of the three, and
 * api/create-post refuses any combination. The composer has to say so while a
 * post is being written rather than at submit time, so choosing one takes the
 * other two away.
 */
function mounted() {
    // Composer.mount reads the real ClientConfig, which reads this cookie, and
    // bails out entirely when there is no signed-in user to compose as.
    document.cookie = 'APP-CONFIG=' + encodeURIComponent(JSON.stringify({
        currentUserId: 2,
        siteURL: 'https://example.test',
        pollDurations: { '1 hour': 60, '1 day': 1440 },
        pollMaxOptions: 4,
    }));

    const form = document.createElement('form');
    form.className = 'Card d-flex flex-column Composer PostComposer';
    document.body.appendChild(form);
    Composer.mount(form);

    return {
        form,
        link: form.querySelector('[name="linkURL"]'),
        file: form.querySelector('[name="files[]"]'),
        poll: form.querySelector('.ComposerPollButton'),
        remove: () => document.body.removeChild(form),
    };
}

const hidden = (element) => element.style.display === 'none';

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

        'all three ways of posting are offered until one is chosen'() {
            const composer = mounted();

            TestCase.assertFalse(hidden(composer.link));
            TestCase.assertFalse(hidden(composer.file));
            TestCase.assertFalse(hidden(composer.poll));

            composer.remove();
        },

        'typing a link puts away the files and the poll'() {
            const composer = mounted();

            composer.link.value = 'https://example.com';
            composer.link.dispatchEvent(new window.Event('input'));

            TestCase.assertTrue(hidden(composer.file));
            TestCase.assertTrue(hidden(composer.poll));

            composer.remove();
        },

        'clearing the link brings the other two back'() {
            const composer = mounted();

            composer.link.value = 'https://example.com';
            composer.link.dispatchEvent(new window.Event('input'));
            composer.link.value = '';
            composer.link.dispatchEvent(new window.Event('input'));

            TestCase.assertFalse(hidden(composer.file));
            TestCase.assertFalse(hidden(composer.poll));

            composer.remove();
        },

        'opening a poll puts away the link and the files'() {
            const composer = mounted();

            composer.poll.click();

            TestCase.assertTrue(hidden(composer.link));
            TestCase.assertTrue(hidden(composer.file));

            composer.remove();
        },

        'withdrawing a poll brings the other two back and empties it'() {
            const composer = mounted();

            composer.poll.click();
            const option = composer.form.querySelector('[name="pollOptions[]"]');
            option.value = 'Yes';

            composer.poll.click();

            TestCase.assertFalse(hidden(composer.link));
            TestCase.assertFalse(hidden(composer.file));
            // Emptied, because the inputs stay in the form either way - text
            // left behind would attach a poll that had been taken back.
            TestCase.assertEquals('', option.value);

            composer.remove();
        },
    }
};
