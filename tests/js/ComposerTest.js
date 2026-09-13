import { TestCase, write_client_config } from './TestCase.js';
import { FormForm, ToggleButton } from '../../scripts/HTMLObjects.js';
import { Api } from '../../scripts/Runtime.js';
import { Composer } from '../../scripts/Controllers.js';

/**
 * A post carries a link, attached media, or a poll - one of the three, and
 * api/create-post refuses any combination. The composer has to say so while a
 * post is being written rather than at submit time, so choosing one takes the
 * other two away.
 */
function mounted(data = {}) {
    // Composer.mount reads the real ClientConfig, which reads this block, and
    // bails out entirely when there is no signed-in user to compose as.
    write_client_config({
        currentUserId: 2,
        siteURL: 'https://example.test',
        pollDurations: { '1 hour': 60, '1 day': 1440 },
        pollMaxOptions: 4,
    });

    const form = document.createElement('form');
    form.className = 'Card Composer PostComposer';
    Object.assign(form.dataset, data);
    document.body.appendChild(form);
    Composer.mount(form);

    return {
        form,
        link: form.querySelector('[name="linkURL"]'),
        file: form.querySelector('.ComposerFileInput'),
        // The label standing in for the input, which is what shows and hides.
        filePicker: form.querySelector('.ComposerFilePicker'),
        poll: form.querySelector('.ComposerPollButton'),
        sensitive: form.querySelector('.SensitiveMediaToggle'),
        sensitiveBox: form.querySelector('[name="sensitive"]'),
        warning: form.querySelector('.ContentWarningInput'),
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

function fakeUploads() {
    const originalXHR = globalThis.XMLHttpRequest, originalFormData = globalThis.FormData;
    const uploads = [];
    globalThis.FormData = window.FormData;
    globalThis.XMLHttpRequest = class extends window.EventTarget {
        upload = new window.EventTarget();
        constructor() { super(); uploads.push(this); }
        open(method, url) { this.method = method; this.url = url; }
        setRequestHeader() {}
        send(body) { this.body = body; }
        abort() { this.aborted = true; this.finish(0, null); }
        finish(status, data) {
            this.status = status;
            this.responseText = JSON.stringify(data);
            this.dispatchEvent(new window.Event('loadend'));
        }
    };
    return { uploads, restore: () => {
        globalThis.XMLHttpRequest = originalXHR;
        globalThis.FormData = originalFormData;
    } };
}

export default {
    suite: 'Composer',
    tests: {
        async 'saving Markdown captures the active editor and warning settings without clearing later edits'() {
            const composer = mounted();
            const original = Api.post;
            const markdown = composer.form.querySelector('.MarkdownInput');
            Composer.getInstance(composer.form).markdownMode = true;
            markdown.value = 'Submitted **Markdown**';
            composer.sensitiveBox.checked = true;
            composer.warning.value = 'Spoilers';
            markdown.dispatchEvent(new window.Event('input'));
            let convert, staged;
            Api.post = (path, body) => {
                if (path === '/api/convert-body') {
                    TestCase.assertEquals('Submitted **Markdown**', body.body);
                    return new Promise(resolve => { convert = resolve; });
                }
                TestCase.assertEquals('/api/stage-post', path);
                staged = body;
                return Promise.resolve({ stagedPostId: 1 });
            };
            try {
                composer.form.querySelector('.ComposerDraftButton').click();
                TestCase.assertNotNull(convert, 'Markdown-only content must reach conversion');
                markdown.value = 'Later text';
                composer.warning.value = 'Later warning';
                convert({ body: '{"ops":[{"insert":"Submitted Markdown"}]}' });
                await new Promise(resolve => setTimeout(resolve, 0));
                TestCase.assertEquals('{"ops":[{"insert":"Submitted Markdown"}]}', staged.description);
                TestCase.assertTrue(staged.sensitive);
                TestCase.assertEquals('Spoilers', staged.contentWarning);
                TestCase.assertEquals('Later text', markdown.value);
                TestCase.assertEquals('Later warning', composer.warning.value);
            } finally { FormForm.cancel(composer.form); Api.post = original; composer.remove(); }
        },
        'opening a saved draft restores its sensitivity and warning'() {
            const composer = mounted({ stagedPostId: '10', title: 'Saved draft', sensitive: '1', contentWarning: 'Saved warning' });
            try {
                TestCase.assertTrue(composer.sensitiveBox.checked);
                TestCase.assertEquals('Saved warning', composer.warning.value);
                TestCase.assertFalse(hidden(composer.warning));
            } finally { composer.remove(); }
        },
        async 'rich text typed during an upload survives its successful response'() {
            const original = globalThis.Quill;
            let quill;
            globalThis.Quill = class extends original {
                body = { ops: [{ insert: 'Submitted body\n' }] };
                constructor(...args) { super(...args); quill = this; }
                getContents() { return this.body; }
                getText() { return this.body.ops.map(op => op.insert).join(''); }
                setText(text) { this.body = { ops: [{ insert: text }] }; }
            };
            const composer = mounted();
            const fake = fakeUploads();
            try {
                const pending = FormForm.submit(composer.form);
                quill.body = { ops: [{ insert: 'New unsaved rich text\n' }] };
                fake.uploads[0].finish(200, { response: { processing: true } });
                await pending;
                TestCase.assertEquals('New unsaved rich text\n', quill.getText());
                TestCase.assertFalse(composer.form.querySelector('[type="submit"]').disabled);
            } finally { FormForm.cancel(composer.form); fake.restore(); composer.remove(); globalThis.Quill = original; }
        },
        async 'markdown written during conversion survives and the map uses submitted coordinates'() {
            const composer = mounted();
            const fake = fakeUploads();
            const original = Api.post;
            const markdown = composer.form.querySelector('.MarkdownInput');
            Composer.getInstance(composer.form).markdownMode = true;
            markdown.value = 'Submitted markdown';
            const latitude = composer.form.querySelector('[name="latitude"]');
            const longitude = composer.form.querySelector('[name="longitude"]');
            latitude.value = '10';
            longitude.value = '20';
            let convert, posted;
            Api.post = () => new Promise(resolve => { convert = resolve; });
            composer.form.addEventListener('composer:posted', event => { posted = event.detail; });
            try {
                const pending = FormForm.submit(composer.form);
                markdown.value = 'New unsaved markdown';
                convert({ body: '{"ops":[{"insert":"Submitted markdown"}]}' });
                await new Promise(resolve => setTimeout(resolve, 0));
                latitude.value = '30';
                longitude.value = '40';
                fake.uploads[0].finish(200, { response: { processing: true } });
                await pending;
                TestCase.assertEquals('New unsaved markdown', markdown.value);
                TestCase.assertEquals('10', posted.latitude);
                TestCase.assertEquals('20', posted.longitude);
                TestCase.assertEquals('30', latitude.value);
            } finally { convert?.(null); FormForm.cancel(composer.form); fake.restore(); Api.post = original; composer.remove(); }
        },
        async 'a successful upload preserves writing and attachments added while it was pending'() {
            const composer = mounted();
            const fake = fakeUploads();
            const title = composer.form.querySelector('[name="title"]');
            title.value = 'Submitted title';
            pick(composer, [imageFile('sent.png')]);
            let posted = 0;
            composer.form.addEventListener('composer:posted', () => { posted++; });
            try {
                const pending = FormForm.submit(composer.form);
                title.value = 'New unsaved title';
                pick(composer, [imageFile('next.png')]);
                fake.uploads[0].finish(200, { response: { processing: true } });
                await pending;
                TestCase.assertEquals('New unsaved title', title.value);
                TestCase.assertEquals(2, composer.form.querySelectorAll('.ComposerAttachment').length);
                TestCase.assertEquals(1, posted, 'the completed post still reaches listeners');
                TestCase.assertFalse(composer.form.querySelector('[type="submit"]').disabled);
            } finally { FormForm.cancel(composer.form); fake.restore(); composer.remove(); }
        },
        async 'saving a draft preserves a newer draft written during the request'() {
            const composer = mounted();
            const original = Api.post;
            const title = composer.form.querySelector('[name="title"]');
            title.value = 'Submitted draft';
            title.dispatchEvent(new window.Event('input'));
            let finish;
            Api.post = () => new Promise(resolve => { finish = resolve; });
            try {
                composer.form.querySelector('.ComposerDraftButton').click();
                title.value = 'New draft';
                finish({ stagedPostId: 1 });
                await new Promise(resolve => setTimeout(resolve, 0));
                TestCase.assertEquals('New draft', title.value);
                TestCase.assertFalse(composer.form.querySelector('.ComposerDraftButton').disabled);
            } finally { finish?.(null); Api.post = original; composer.remove(); }
        },
        async 'upload stays guarded until XHR completes and successful reset disables empty submission'() {
            const composer = mounted();
            const fake = fakeUploads();
            const button = composer.form.querySelector('[type="submit"]');
            let posted = 0;
            composer.form.addEventListener('composer:posted', () => { posted++; });
            pick(composer, [imageFile('cat.png')]);
            try {
                const first = FormForm.submit(composer.form);
                await FormForm.submit(composer.form);
                TestCase.assertEquals(1, fake.uploads.length);
                TestCase.assertTrue(FormForm.isPending(composer.form));
                TestCase.assertTrue(button.disabled && button.classList.contains('Working'));
                TestCase.assertEquals('cat.png', fake.uploads[0].body.get('files[]').name);
                TestCase.assertEquals(1, fake.uploads[0].body.getAll('altTexts[]').length);
                fake.uploads[0].upload.dispatchEvent(new window.ProgressEvent('progress', { lengthComputable: true, loaded: 4, total: 10 }));
                TestCase.assertEquals(4, composer.form.querySelector('progress').value);
                composer.form.querySelector('[name="title"]').dispatchEvent(new window.Event('input'));
                TestCase.assertTrue(button.disabled, 'editing cannot unlock a pending submission');
                fake.uploads[0].finish(500, { error: 'Upload failed' });
                await first;
                TestCase.assertFalse(FormForm.isPending(composer.form));
                TestCase.assertFalse(button.disabled, 'content remains for retry');
                TestCase.assertFalse(composer.form.querySelector('progress').classList.contains('Active'));
                const retry = FormForm.submit(composer.form);
                fake.uploads[1].finish(200, { response: { processing: true } });
                await retry;
                TestCase.assertEquals(1, posted);
                TestCase.assertTrue(button.disabled, 'the successful reset leaves an empty composer');
                TestCase.assertFalse(button.classList.contains('Working'));
            } finally { FormForm.cancel(composer.form); fake.restore(); composer.remove(); }
        },
        async 'cancelling an upload cannot clear a replacement uploads progress or pending state'() {
            const composer = mounted();
            const fake = fakeUploads();
            pick(composer, [imageFile('cat.png')]);
            try {
                const first = FormForm.submit(composer.form);
                FormForm.cancel(composer.form);
                TestCase.assertTrue(fake.uploads[0].aborted);
                const second = FormForm.submit(composer.form);
                await first;
                TestCase.assertTrue(FormForm.isPending(composer.form));
                TestCase.assertTrue(composer.form.querySelector('progress').classList.contains('Active'));
                fake.uploads[1].finish(500, { error: 'Retry later' });
                await second;
                TestCase.assertFalse(FormForm.isPending(composer.form));
            } finally { FormForm.cancel(composer.form); fake.restore(); composer.remove(); }
        },
        async 'Save Draft and Enter share one guard through the staged-post request'() {
            const composer = mounted();
            const original = Api.post;
            const title = composer.form.querySelector('[name="title"]');
            title.value = 'A draft';
            title.dispatchEvent(new window.Event('input'));
            let finish, calls = 0;
            Api.post = async (path, payload) => {
                TestCase.assertEquals('/api/stage-post', path);
                TestCase.assertNull(payload.publishAtEpoch);
                calls++;
                return new Promise(resolve => { finish = resolve; });
            };
            try {
                composer.form.querySelector('.ComposerDraftButton').click();
                await FormForm.submit(composer.form);
                TestCase.assertEquals(1, calls);
                TestCase.assertTrue(FormForm.isPending(composer.form));
                TestCase.assertTrue(composer.form.querySelector('.ComposerScheduleButton').disabled);
                finish({ stagedPostId: 1 });
                await new Promise(resolve => setTimeout(resolve, 0));
                TestCase.assertFalse(FormForm.isPending(composer.form));
                TestCase.assertTrue(composer.form.querySelector('[type="submit"]').disabled);
                TestCase.assertTrue(composer.form.querySelector('.ComposerDraftButton').disabled);
            } finally { finish?.(null); Api.post = original; composer.remove(); }
        },
        'the writing area explains itself and points at the plain-text way'() {
            const composer = mounted();

            const help = composer.form.querySelector('.ComposerEditorHelp');

            TestCase.assertNotNull(help);
            TestCase.assertTrue(help.classList.contains('visually-hidden'), 'said, not shown');
            TestCase.assertTrue(help.textContent.includes('Use Markdown'), 'names the button that swaps it');

            composer.remove();
        },
        'the plain-text box is named and explained too'() {
            const composer = mounted();

            const markdown = composer.form.querySelector('.MarkdownInput');
            const label = composer.form.querySelector('label[for="' + markdown.id + '"]');

            TestCase.assertNotNull(label);
            TestCase.assertTrue(label.classList.contains('visually-hidden'), 'said, not shown');
            TestCase.assertEquals('ComposerMarkdownHelp', markdown.getAttribute('aria-describedby'));
            TestCase.assertNotNull(composer.form.querySelector('#ComposerMarkdownHelp'));

            composer.remove();
        },
        'every writing field is named by a hidden label'() {
            const composer = mounted();

            for (const input of [
                composer.form.querySelector('[name="title"]'),
                composer.form.querySelector('[name="linkURL"]'),
                composer.form.querySelector('.ContentWarningInput'),
                composer.form.querySelector('.PollOptionInput'),
                composer.form.querySelector('.PollDurationSelect'),
                composer.form.querySelector('.ComposerScheduleDate'),
                composer.form.querySelector('.ComposerScheduleTime'),
            ]) {
                const label = composer.form.querySelector('label[for="' + input.id + '"]');

                TestCase.assertNotNull(label, input.className + ' has a label');
                TestCase.assertTrue(label.classList.contains('visually-hidden'), input.className + ' label is hidden');
                TestCase.assertTrue(label.textContent.trim() !== '', input.className + ' label says something');
            }

            composer.remove();
        },
        'the file tally is shown against the cap, and leaves with the files'() {
            const composer = mounted();
            const counter = composer.form.querySelector('.ComposerAttachmentCount');

            TestCase.assertNotNull(counter);
            TestCase.assertEquals('', counter.textContent);

            pick(composer, [imageFile('cat.png'), imageFile('dog.png')]);
            TestCase.assertTrue(counter.textContent.startsWith('2 / '), 'counts what is attached');

            composer.form.querySelector('.ComposerFilesRemoveButton').click();
            TestCase.assertEquals('', counter.textContent);

            composer.remove();
        },
        'attaching a file says so'() {
            const composer = mounted();

            pick(composer, [imageFile('cat.png')]);

            const status = composer.form.querySelector('.ComposerStatus');

            TestCase.assertNotNull(status);
            TestCase.assertEquals('polite', status.getAttribute('aria-live'));
            TestCase.assertTrue(status.textContent.includes('1 file attached'), status.textContent);

            composer.remove();
        },

        'init runs without error when PostComposer is present'() {
            const form = document.createElement('form');
            form.className = 'Card Composer PostComposer';
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
            TestCase.assertFalse(hidden(composer.filePicker));
            TestCase.assertFalse(hidden(composer.poll));

            composer.remove();
        },

        'typing a link puts away the files and the poll'() {
            const composer = mounted();

            composer.link.value = 'https://example.com';
            composer.link.dispatchEvent(new window.Event('input'));

            TestCase.assertTrue(hidden(composer.filePicker));
            TestCase.assertTrue(hidden(composer.poll));

            composer.remove();
        },

        'clearing the link brings the other two back'() {
            const composer = mounted();

            composer.link.value = 'https://example.com';
            composer.link.dispatchEvent(new window.Event('input'));
            composer.link.value = '';
            composer.link.dispatchEvent(new window.Event('input'));

            TestCase.assertFalse(hidden(composer.filePicker));
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
            TestCase.assertFalse(hidden(composer.filePicker));
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

        // Words need warning about at least as often as pictures do, and a
        // spoiler is usually text - so the mark is offered on every post,
        // whether or not anything is attached.
        'sensitive is offered on a post with no files'() {
            const composer = mounted();

            TestCase.assertFalse(hidden(composer.sensitive));

            pick(composer, [imageFile('cat.png')]);
            TestCase.assertFalse(hidden(composer.sensitive));

            composer.form.querySelector('.ComposerFilesRemoveButton').click();
            TestCase.assertFalse(hidden(composer.sensitive), 'and it stays when the files go');

            composer.remove();
        },

        'the warning field appears with the mark and is emptied when it comes off'() {
            const composer = mounted();

            TestCase.assertTrue(hidden(composer.warning), 'nothing to warn about until the post is marked');

            composer.sensitiveBox.checked = true;
            composer.sensitiveBox.dispatchEvent(new window.Event('change'));

            TestCase.assertFalse(hidden(composer.warning));

            composer.warning.value = 'Spoilers';
            composer.sensitiveBox.checked = false;
            composer.sensitiveBox.dispatchEvent(new window.Event('change'));

            TestCase.assertTrue(hidden(composer.warning));
            // Emptied as it goes, so a warning cannot ride along on a post
            // nobody flagged and gate a body the author had un-warned.
            TestCase.assertEquals('', composer.warning.value);

            composer.remove();
        },

        'the warning says it is optional'() {
            const composer = mounted();

            TestCase.assertEquals('Content Warning (optional)', composer.warning.placeholder);

            composer.remove();
        },

        'opening a poll puts away the link and the files'() {
            const composer = mounted();

            composer.poll.click();

            TestCase.assertTrue(hidden(composer.link));
            TestCase.assertTrue(hidden(composer.filePicker));

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
            TestCase.assertFalse(hidden(composer.filePicker));
            // Emptied, because the inputs stay in the form either way - text
            // left behind would attach a poll that had been taken back.
            TestCase.assertEquals('', option.value);

            composer.remove();
        },
    }
};
