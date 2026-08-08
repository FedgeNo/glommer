import { TestCase, write_client_config } from './TestCase.js';
import { ToggleButton } from '../../scripts/ToggleButton.js';
import { Composer } from '../../scripts/Composer.js';

/**
 * A post carries a link, attached media, or a poll - one of the three, and
 * api/create-post refuses any combination. The composer has to say so while a
 * post is being written rather than at submit time, so choosing one takes the
 * other two away.
 */
function mounted() {
    // Composer.mount reads the real ClientConfig, which reads this block, and
    // bails out entirely when there is no signed-in user to compose as.
    write_client_config({
        currentUserId: 2,
        siteURL: 'https://example.test',
        pollDurations: { '1 hour': 60, '1 day': 1440 },
        pollMaxOptions: 4,
    });

    const form = document.createElement('form');
    form.className = 'Card d-flex flex-column Composer PostComposer';
    document.body.appendChild(form);
    Composer.mount(form);

    return {
        form,
        link: form.querySelector('[name="linkURL"]'),
        file: form.querySelector('.ComposerFileInput'),
        poll: form.querySelector('.ComposerPollButton'),
        sensitive: form.querySelector('.SensitiveMediaToggle'),
        remove: () => document.body.removeChild(form),
    };
}

const hidden = (element) => element.style.display === 'none';

const imageFile = (name) => new window.File(['x'], name, { type: 'image/png' });
const videoFile = (name) => new window.File(['x'], name, { type: 'video/mp4' });

/** Hands the picker a fresh selection, the way choosing files in it would. */
function pick(composer, files) {
    Object.defineProperty(composer.file, 'files', { value: files, configurable: true });
    composer.file.dispatchEvent(new window.Event('change'));
    Object.defineProperty(composer.file, 'files', { value: [], configurable: true });
}

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

        'choosing files lists them, and the picker stays for adding more'() {
            const composer = mounted();
            const remove = composer.form.querySelector('.ComposerFilesRemoveButton');

            pick(composer, [imageFile('cat.png'), videoFile('clip.mp4')]);

            // The rows are the files now; the picker staying open is how a
            // third file joins the two.
            const rows = composer.form.querySelectorAll('.ComposerAttachment');
            TestCase.assertEquals(2, rows.length);
            TestCase.assertFalse(hidden(composer.file));
            TestCase.assertFalse(hidden(remove));

            // A second pick adds rather than replaces.
            pick(composer, [imageFile('dog.png')]);
            TestCase.assertEquals(3, composer.form.querySelectorAll('.ComposerAttachment').length);

            composer.remove();
        },

        'only an image row offers alt text'() {
            const composer = mounted();

            pick(composer, [imageFile('cat.png'), videoFile('clip.mp4')]);

            const rows = composer.form.querySelectorAll('.ComposerAttachment');
            TestCase.assertNotNull(rows[0].querySelector('.ComposerAttachmentAltInput'));
            TestCase.assertNull(rows[1].querySelector('.ComposerAttachmentAltInput'));

            composer.remove();
        },

        'removing a row removes exactly that file'() {
            const composer = mounted();

            pick(composer, [imageFile('cat.png'), imageFile('dog.png')]);

            const rows = composer.form.querySelectorAll('.ComposerAttachment');
            rows[0].querySelector('.ComposerAttachmentRemoveButton').click();

            const names = [...composer.form.querySelectorAll('.ComposerAttachmentName')]
                .map((name) => name.textContent);
            TestCase.assertEquals(1, names.length);
            TestCase.assertEquals('dog.png', names[0]);

            composer.remove();
        },

        'the hundred-and-first file is refused, not silently truncated later'() {
            const composer = mounted();

            const batch = [];
            for (let i = 0; i < Composer.MAX_FILES + 2; i++) {
                batch.push(imageFile('photo-' + i + '.png'));
            }
            pick(composer, batch);

            TestCase.assertEquals(Composer.MAX_FILES, composer.form.querySelectorAll('.ComposerAttachment').length);

            // Still full after another pick - the cap holds across picks, which
            // is the whole reason it exists client-side.
            pick(composer, [imageFile('one-more.png')]);
            TestCase.assertEquals(Composer.MAX_FILES, composer.form.querySelectorAll('.ComposerAttachment').length);

            composer.remove();
        },

        'removing the last file removes the list itself'() {
            const composer = mounted();
            const remove = composer.form.querySelector('.ComposerFilesRemoveButton');

            pick(composer, [imageFile('cat.png')]);
            TestCase.assertNotNull(composer.form.querySelector('.ComposerAttachmentList'));

            composer.form.querySelector('.ComposerAttachmentRemoveButton').click();

            TestCase.assertNull(composer.form.querySelector('.ComposerAttachmentList'));
            TestCase.assertTrue(hidden(remove));

            composer.remove();
        },

        'scheduling disarms the submit until there is a post and a future day'() {
            const composer = mounted();
            const schedule = composer.form.querySelector('.ComposerScheduleButton');
            const date = composer.form.querySelector('.ComposerScheduleDate');
            const title = composer.form.querySelector('[name="title"]');
            const submit = composer.form.querySelector('button[type="submit"]');

            schedule.click();

            // Armed the moment the clock comes out: right label, no click yet.
            TestCase.assertEquals('Schedule Post', ToggleButton.selected(submit));
            TestCase.assertTrue(submit.disabled);

            // A future day alone is not enough - there is nothing to schedule.
            date.value = new Date(Date.now() + 86400000 * 2).toISOString().slice(0, 10);
            date.dispatchEvent(new window.Event('input'));
            TestCase.assertTrue(submit.disabled);

            // Content plus the day is.
            title.value = 'A titled post';
            title.dispatchEvent(new window.Event('input'));
            TestCase.assertFalse(submit.disabled);

            // A past day disarms it again, client-side, before the server
            // would refuse it anyway.
            date.value = '2020-01-01';
            date.dispatchEvent(new window.Event('input'));
            TestCase.assertTrue(submit.disabled);

            // Putting the schedule away hands back an ordinary Post button.
            schedule.click();
            TestCase.assertEquals('Post', ToggleButton.selected(submit));
            TestCase.assertFalse(submit.disabled);

            composer.remove();
        },

        'an empty composer offers no live Post or Save Draft'() {
            const composer = mounted();
            const submit = composer.form.querySelector('button[type="submit"]');
            const draft = composer.form.querySelector('.ComposerDraftButton');
            const title = composer.form.querySelector('[name="title"]');

            TestCase.assertTrue(submit.disabled);
            TestCase.assertTrue(draft.disabled);

            title.value = 'Something to say';
            title.dispatchEvent(new window.Event('input'));

            TestCase.assertFalse(submit.disabled);
            TestCase.assertFalse(draft.disabled);

            title.value = '';
            title.dispatchEvent(new window.Event('input'));

            TestCase.assertTrue(submit.disabled);
            TestCase.assertTrue(draft.disabled);

            composer.remove();
        },

        'files alone arm Post but never Save Draft'() {
            const composer = mounted();
            const submit = composer.form.querySelector('button[type="submit"]');
            const draft = composer.form.querySelector('.ComposerDraftButton');

            pick(composer, [imageFile('cat.png')]);

            // A media post needs no words; a draft can't carry files at all.
            TestCase.assertFalse(submit.disabled);
            TestCase.assertTrue(draft.disabled);

            composer.remove();
        },

        'the schedule toggle wears the removal colour only while removing'() {
            const composer = mounted();
            const schedule = composer.form.querySelector('.ComposerScheduleButton');

            TestCase.assertFalse(schedule.classList.contains('Removing'));

            schedule.click();
            TestCase.assertEquals('Remove Schedule', ToggleButton.selected(schedule));
            TestCase.assertTrue(schedule.classList.contains('Removing'));

            schedule.click();
            TestCase.assertEquals('Add Schedule', ToggleButton.selected(schedule));
            TestCase.assertFalse(schedule.classList.contains('Removing'));

            composer.remove();
        },

        'sensitive is offered only once there are files for it to be about'() {
            const composer = mounted();
            const box = composer.sensitive.querySelector('[name="sensitive"]');

            TestCase.assertTrue(hidden(composer.sensitive));

            pick(composer, [imageFile('cat.png')]);

            TestCase.assertFalse(hidden(composer.sensitive));

            box.checked = true;
            composer.form.querySelector('.ComposerFilesRemoveButton').click();

            TestCase.assertTrue(hidden(composer.sensitive));
            // Cleared with them, so it cannot ride along on a post with no media.
            TestCase.assertFalse(box.checked);

            composer.remove();
        },

        'opening a poll puts away the link and the files'() {
            const composer = mounted();

            composer.poll.click();

            TestCase.assertTrue(hidden(composer.link));
            TestCase.assertTrue(hidden(composer.file));

            composer.remove();
        },

        'the poll button warns while it is the one that takes the poll away'() {
            const composer = mounted();

            TestCase.assertFalse(composer.poll.classList.contains('Removing'));

            composer.poll.click();
            TestCase.assertTrue(composer.poll.classList.contains('Removing'));

            // Back to an ordinary button once there is nothing left to remove.
            composer.poll.click();
            TestCase.assertFalse(composer.poll.classList.contains('Removing'));

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
