import { TestCase } from './TestCase.js';
import { FormForm } from '../../scripts/HTMLObjects.js';
import { Cookie, Toast } from '../../scripts/Runtime.js';

function deferred() {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
}

function formWithButtons(className = '') {
    const form = new FormForm().toDOM();
    if (className) form.classList.add(className);
    for (let index = 0; index < 2; index++) {
        const button = document.createElement('button');
        button.type = 'submit';
        form.append(button);
    }
    document.body.append(form);
    return form;
}

const tick = () => new Promise(resolve => setTimeout(resolve, 0));
const submit = (form, submitter) => form.dispatchEvent(new window.SubmitEvent('submit', {
    bubbles: true, cancelable: true, submitter,
}));

export default {
    suite: 'FormForm',
    tests: {
        async 'reset preserves changed radio groups, select values and replacement files'() {
            const form = formWithButtons();
            const radios = ['default', 'next', 'submitted'].map(value => {
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'choice';
                input.value = value;
                form.append(input);
                return input;
            });
            radios[0].defaultChecked = true;
            radios[2].checked = true;
            const select = document.createElement('select');
            for (const value of ['default', 'submitted', 'next']) {
                const option = document.createElement('option');
                option.value = value;
                select.append(option);
            }
            select.value = 'submitted';
            const file = document.createElement('input');
            file.type = 'file';
            const originalFile = new window.File(['old'], 'same-name.txt');
            const replacement = new window.File(['new'], 'same-name.txt');
            Object.defineProperty(file, 'files', { value: [originalFile], configurable: true });
            const unchanged = document.createElement('textarea');
            unchanged.defaultValue = 'Default text';
            unchanged.value = 'Submitted text';
            form.append(select, file, unchanged);
            const completion = FormForm.completion(form);
            try {
                radios[1].checked = true;
                select.value = 'next';
                Object.defineProperty(file, 'files', { value: [replacement], configurable: true });
                let clears = 0;
                file.addEventListener('input', () => { clears++; });
                completion.reset();
                TestCase.assertTrue(radios[1].checked);
                TestCase.assertFalse(radios[0].checked);
                TestCase.assertEquals('next', select.value);
                TestCase.assertEquals(0, clears, 'a replacement file with the same filename is a new selection');
                TestCase.assertEquals('Default text', unchanged.value);
            } finally { form.remove(); }
        },
        async 'completion clears submitted values but preserves newer text and new controls'() {
            const form = formWithButtons();
            const sent = document.createElement('textarea');
            sent.value = 'Submitted text';
            const untouched = document.createElement('input');
            untouched.value = 'Submitted value';
            form.append(sent, untouched);
            const wait = deferred();
            let changes = 0;
            untouched.addEventListener('input', () => { changes++; });
            const pending = FormForm.run(form, async (form, { clear }) => { await wait.promise; clear(); });
            sent.value = 'My next message';
            const added = document.createElement('input');
            added.value = 'Written after sending';
            form.append(added);
            try {
                wait.resolve();
                await pending;
                TestCase.assertEquals('My next message', sent.value);
                TestCase.assertEquals('', untouched.value);
                TestCase.assertEquals('Written after sending', added.value);
                TestCase.assertEquals(Cookie.get('CSRF-TOKEN') ?? '', form.elements.CSRFToken.value);
                TestCase.assertEquals(1, changes, 'clearing notifies input listeners and autofill');
            } finally { wait.resolve(); form.remove(); }
        },
        async 'cancelled completion cannot reset fields used by a subsequent submission'() {
            const form = formWithButtons();
            const input = document.createElement('input');
            input.value = 'Keep this';
            form.append(input);
            const wait = deferred();
            const pending = FormForm.run(form, async (form, { reset }) => { await wait.promise; reset(); });
            try {
                FormForm.cancel(form);
                wait.resolve();
                await pending;
                TestCase.assertEquals('Keep this', input.value);
            } finally { wait.resolve(); form.remove(); }
        },
        async 'class registration covers existing and inserted forms without duplicate listeners'() {
            const first = formWithButtons('SharedSubmissionTest');
            let calls = 0;
            FormForm.attach('SharedSubmissionTest', () => { throw new Error('stale binding'); });
            FormForm.attach('SharedSubmissionTest', () => { calls++; });
            const second = formWithButtons('SharedSubmissionTest');
            try {
                TestCase.assertFalse(submit(first), 'registered submission prevents native navigation');
                TestCase.assertFalse(submit(second));
                await tick();
                TestCase.assertEquals(2, calls);
            } finally { first.remove(); second.remove(); }
        },
        async 'generated forms use onSubmit and preserve subclass identities and POST CSRF'() {
            class GeneratedForm extends FormForm {}
            let received = null;
            const object = new GeneratedForm({ onSubmit: form => { received = form; } });
            const form = object.toDOM();
            document.body.append(form);
            try {
                TestCase.assertEquals('Form GeneratedForm', form.className);
                TestCase.assertEquals('POST', form.getAttribute('method'));
                TestCase.assertEquals(Cookie.get('CSRF-TOKEN') ?? '', form.elements.CSRFToken.value);
                submit(form);
                await tick();
                TestCase.assertTrue(received === form);
                TestCase.assertThrows(() => object.toDOM());
                TestCase.assertThrows(() => new FormForm({ method: 'DELETE' }).toDOM());
            } finally { form.remove(); }
        },
        async 'specific form bindings override the shared class handler'() {
            const form = formWithButtons('SpecificSubmissionTest');
            let calls = 0;
            FormForm.attach('SpecificSubmissionTest', () => { throw new Error('class handler ran'); });
            FormForm.attach(form, () => { calls++; });
            try {
                await FormForm.submit(form);
                TestCase.assertEquals(1, calls);
            } finally { form.remove(); }
        },
        async 'Enter and repeated submit events share one guard and the clicked button pulses'() {
            const form = formWithButtons();
            const [first, second] = form.querySelectorAll('button');
            const wait = deferred();
            let calls = 0;
            let context;
            FormForm.attach(form, async (form, received) => { calls++; context = received; await wait.promise; });
            try {
                submit(form, second);
                submit(form);
                await FormForm.submit(form, first);
                TestCase.assertEquals(1, calls);
                TestCase.assertTrue(context.submitter === second);
                TestCase.assertTrue(first.disabled && second.disabled);
                TestCase.assertFalse(first.classList.contains('Working'));
                TestCase.assertTrue(second.classList.contains('Working'));
                TestCase.assertEquals('true', form.getAttribute('aria-busy'));
                wait.resolve();
                await tick();
                TestCase.assertFalse(FormForm.isPending(form));
                TestCase.assertFalse(first.disabled || second.disabled);
            } finally { wait.resolve(); form.remove(); }
        },
        async 'failure restores existing disabled and accessibility states before settled'() {
            const form = formWithButtons();
            const [first, second] = form.querySelectorAll('button');
            const input = document.createElement('input');
            form.append(input);
            first.disabled = true;
            second.setAttribute('aria-busy', 'false');
            form.setAttribute('aria-busy', 'false');
            let settled = false;
            try {
                let error;
                try {
                    await FormForm.run(form, () => {
                        TestCase.assertTrue(input.disabled);
                        throw new Error('handler failed');
                    }, {
                        submitter: second,
                        controls: () => [input],
                        settled: () => {
                            TestCase.assertFalse(FormForm.isPending(form));
                            TestCase.assertFalse(input.disabled);
                            settled = true;
                        },
                    });
                } catch (caught) { error = caught; }
                TestCase.assertEquals('handler failed', error?.message);
                TestCase.assertTrue(settled && first.disabled);
                TestCase.assertFalse(second.disabled);
                TestCase.assertEquals('false', form.getAttribute('aria-busy'));
                TestCase.assertEquals('false', second.getAttribute('aria-busy'));
            } finally { form.remove(); }
        },
        async 'cancel releases immediately and an old completion cannot release a newer run'() {
            const form = formWithButtons();
            const old = deferred(), newer = deferred();
            let signal;
            const oldRun = FormForm.run(form, async (form, context) => { signal = context.signal; await old.promise; });
            try {
                FormForm.cancel(form);
                TestCase.assertTrue(signal.aborted);
                TestCase.assertFalse(FormForm.isPending(form));
                const newRun = FormForm.run(form, () => newer.promise);
                old.resolve();
                await oldRun;
                TestCase.assertTrue(FormForm.isPending(form));
                TestCase.assertTrue(form.querySelector('button').disabled);
                newer.resolve();
                await newRun;
                TestCase.assertFalse(FormForm.isPending(form));
            } finally { old.resolve(); newer.resolve(); form.remove(); }
        },
        async 'buttonless and silent actions still have a form guard'() {
            const form = new FormForm().toDOM();
            await FormForm.run(form, (form, { submitter }) => {
                TestCase.assertNull(submitter);
                TestCase.assertTrue(FormForm.isPending(form));
            }, { submitter: null });
            TestCase.assertFalse(FormForm.isPending(form));
        },
        async 'an unexpected delegated exception reports once and releases for retry'() {
            const form = formWithButtons();
            const originalToast = Toast.show, originalError = console.error;
            let toasts = 0;
            Toast.show = () => { toasts++; };
            console.error = () => {};
            FormForm.attach(form, () => { throw new Error('failed'); });
            try {
                submit(form);
                await tick();
                TestCase.assertEquals(1, toasts);
                TestCase.assertFalse(FormForm.isPending(form));
                TestCase.assertFalse(form.querySelector('button').disabled);
            } finally { Toast.show = originalToast; console.error = originalError; form.remove(); }
        },
        'unregistered native forms retain their normal POST navigation'() {
            const form = formWithButtons();
            try { TestCase.assertTrue(submit(form)); } finally { form.remove(); }
        },
    },
};
