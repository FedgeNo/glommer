import { TestCase } from './TestCase.js';
import '../../scripts/Controllers.js';

/**
 * Saving the mail settings.
 *
 * The thing worth holding to account is not any one field but the rule: what
 * the form shows is what gets sent. A field the form renders and the payload
 * leaves out does not simply fail to save - the endpoint reads it as blank and
 * writes that, so the setting is wiped by the act of saving something else.
 * That is exactly how the "from" name got lost.
 */

/** The fields the PHP form renders, with something in each. */
const FIELDS = {
    mailFromAddress: 'post@example.test',
    mailFromName: 'A Site',
    smtpHost: 'smtp.example.test',
    smtpPort: '587',
    smtpUsername: 'somebody',
    smtpPassword: 'a-secret',
    smtpEncryption: 'tls',
};

function buildForm() {
    document.body.replaceChildren();

    const form = document.createElement('form');
    form.className = 'Form MailSettingsForm';

    for (const [name, value] of Object.entries(FIELDS)) {
        const input = document.createElement('input');
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }

    const submit = document.createElement('button');
    submit.type = 'submit';
    form.appendChild(submit);

    document.body.appendChild(form);

    return form;
}

async function submitAndCapture(form) {
    const original = globalThis.fetch;
    let sent = null;

    globalThis.fetch = async (url, options) => {
        sent = { url: String(url), payload: JSON.parse(options.body) };

        return { ok: true, json: async () => ({ response: { saved: true } }) };
    };

    form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));

    // The handler is async - let it reach the fetch and come back.
    await new Promise((resolve) => setTimeout(resolve, 0));

    globalThis.fetch = original;

    return sent;
}

export default {
    suite: 'MailSettingsForm',
    tests: {
        async 'every field the form shows is in what it sends'() {
            const form = buildForm();
            const sent = await submitAndCapture(form);

            TestCase.assertNotNull(sent);

            const shown = [...form.querySelectorAll('[name]')].map((field) => field.name);
            const missing = shown.filter((name) => !(name in sent.payload));

            TestCase.assertEquals('', missing.join(','));
        },
        async 'the from address and name reach the endpoint with their values'() {
            const sent = await submitAndCapture(buildForm());

            TestCase.assertEquals(FIELDS.mailFromAddress, sent.payload.mailFromAddress);
            TestCase.assertEquals(FIELDS.mailFromName, sent.payload.mailFromName);
        },
        async 'it saves to the mail endpoint'() {
            const sent = await submitAndCapture(buildForm());

            TestCase.assertTrue(sent.url.endsWith('/api/mail-settings'));
        },
    },
};
