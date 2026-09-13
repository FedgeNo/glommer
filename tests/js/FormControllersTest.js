import { TestCase } from './TestCase.js';
import { Api } from '../../scripts/Runtime.js';
import { Dialog, FormForm, MessageCrypto, Post } from '../../scripts/HTMLObjects.js';
import { MessageUnlockForm, PostEditor } from '../../scripts/Controllers.js';

// Exercise every API form's real handler with a delayed refusal. This catches
// dropped registrations, changed endpoints and handlers that return too soon,
// without performing writes or navigating away from the test document.
const cases = [
    ['AccountDeleteForm', 'delete-account', 'currentPassword'],
    ['AccountMigrationForm', 'account-migration', 'movedTo alsoKnownAs'],
    ['AvatarUploadForm', 'upload-avatar', 'avatar'],
    ['ServerBlockForm', 'block-server', 'domain reason'],
    ['BotProtectionSettingsForm', 'turnstile-settings', 'turnstileSiteKey turnstileSecretKey recaptchaSiteKey recaptchaSecretKey'],
    ['EmailChangeForm', 'change-email', 'newEmail currentPassword'],
    ['EmailDigestSettingsForm', 'email-digest-settings', 'emailDigestText'],
    ['FaviconSettingsForm', 'favicon-settings', 'favicon'],
    ['FrontPageImageSettingsForm', 'front-page-image', 'frontPageImage'],
    ['GoogleAuthSettingsForm', 'google-auth-settings', 'googleAuthClientId googleAuthSecret'],
    ['LoginForm', 'login', 'identifier password rememberMe'],
    ['LogoutForm', 'logout', ''],
    ['MailSettingsForm', 'mail-settings', 'mailFromAddress mailFromName smtpHost smtpPort smtpUsername smtpPassword smtpEncryption'],
    ['MapSettingsForm', 'map-settings', 'mapTileURL mapTileAPIKey mapTileAttribution'],
    ['MessageComposer', 'send-message', 'body recipientId'],
    ['OpenRouterSettingsForm', 'openrouter-settings', 'openRouterAPIKey openRouterModel openRouterNeverSpend clearOpenRouterAPIKey'],
    ['PasswordChangeForm', 'change-password', 'currentPassword newPassword confirmPassword'],
    ['PasswordResetForm', 'reset-password', 'token newPassword confirmPassword'],
    ['PasswordResetRequestForm', 'forgot-password', 'email'],
    ['RelaySubscribeForm', 'subscribe-relay', 'actorURI followObject'],
    ['RemoteFollowsForm', 'follow-remote', 'handles'],
    ['SignupForm', 'signup', 'username email displayName description password rememberMe'],
    ['SiteInfoSettingsForm', 'terms-settings', 'termsText'],
    ['TwoFactorForm', 'verify-2fa', 'code'],
    ['TwoFactorSettingsForm', 'two-factor', 'currentPassword'],
    ['MessageKeySetupForm', 'message-keys', 'passphrase passphraseConfirm setupAccountPassword'],
    ['MessageKeyPassphraseForm', 'message-keys', 'newPassphrase newPassphraseConfirm currentPassphrase rewrapAccountPassword'],
];

const tick = () => new Promise(resolve => setTimeout(resolve, 0));
const tests = {};

for (const encrypted of [false, true]) {
    tests[(encrypted ? 'encrypted' : 'plaintext') + ' message completion preserves the next message'] = async () => {
        const main = document.createElement('main');
        const list = document.createElement('ul');
        list.className = 'MessageList';
        const form = new FormForm().toDOM();
        form.classList.add('MessageComposer');
        const body = document.createElement('textarea');
        body.name = 'body';
        body.value = 'Submitted message';
        const recipient = document.createElement('input');
        recipient.name = 'recipientId';
        recipient.value = '2';
        form.append(body, recipient);
        main.append(list, form);
        if (encrypted) {
            const unlock = document.createElement('form');
            unlock.className = 'MessageUnlockForm';
            main.append(unlock);
        }
        document.body.append(main);
        const originals = { post: Api.post, scroll: window.scrollTo, encrypt: MessageCrypto.encrypt, key: MessageCrypto.threadKey };
        let finish, sent, encryptedText;
        MessageCrypto.threadKey = () => ({});
        MessageCrypto.encrypt = async (key, text) => { encryptedText = text; return 'encrypted-envelope'; };
        window.scrollTo = () => {};
        Api.post = (path, payload) => { sent = payload; return new Promise(resolve => { finish = resolve; }); };
        const response = { messageId: 11, senderId: 1, recipientId: 2, body: 'Submitted message', createdAt: '2026-09-12 12:00:00' };
        try {
            const first = FormForm.submit(form);
            body.value = 'My next message';
            await tick();
            TestCase.assertEquals('Submitted message', encrypted ? encryptedText : sent.body);
            finish(response);
            await first;
            TestCase.assertEquals('My next message', body.value);
            TestCase.assertEquals(1, list.querySelectorAll('.Message').length);
            const second = FormForm.submit(form);
            await tick();
            finish({ ...response, messageId: 12, body: 'My next message' });
            await second;
            TestCase.assertEquals('', body.value, 'an unchanged sent message is cleared');
        } finally {
            finish?.(null);
            Api.post = originals.post;
            window.scrollTo = originals.scroll;
            MessageCrypto.encrypt = originals.encrypt;
            MessageCrypto.threadKey = originals.key;
            main.remove();
        }
    };
}

for (const [name, fields, response] of [
    ['PasswordChangeForm', ['currentPassword', 'newPassword', 'confirmPassword'], { changed: true }],
    ['RemoteFollowsForm', ['handles'], { results: [], unprocessed: [] }],
]) {
    tests[name + ' completion preserves inputs edited during the request'] = async () => {
        const form = new FormForm().toDOM();
        form.classList.add(name);
        for (const name of fields) {
            const input = document.createElement('input');
            input.name = name;
            input.value = 'Submitted value';
            form.append(input);
        }
        const original = Api.post;
        let finish;
        Api.post = () => new Promise(resolve => { finish = resolve; });
        try {
            const pending = FormForm.submit(form);
            form.elements[fields[0]].value = 'New unsaved value';
            finish(response);
            await pending;
            TestCase.assertEquals('New unsaved value', form.elements[fields[0]].value);
            for (const name of fields.slice(1)) TestCase.assertEquals('', form.elements[name].value);
        } finally { finish?.(null); Api.post = original; }
    };
}

tests['saving an inline edit updates the post while preserving newer changes in the open editor'] = async () => {
    const post = document.createElement('article');
    post.className = 'Post';
    post.dataset.postId = '42';
    post.dataset.title = 'Submitted title';
    post.dataset.descriptionDelta = '{"ops":[]}';
    const content = document.createElement('div');
    content.className = 'PostContent';
    const button = document.createElement('button');
    post.append(content, button);
    document.body.append(post);
    const originals = { post: Api.post, build: Post.fromData };
    let finish, form;
    Api.post = () => new Promise(resolve => { finish = resolve; });
    Post.fromData = data => ({ postElement: () => {
        const content = document.createElement('div');
        content.className = 'PostContent';
        content.textContent = data.title;
        return content;
    } });
    try {
        PostEditor.open(button);
        form = post.nextElementSibling;
        const pending = FormForm.submit(form);
        form.elements.title.value = 'New unsaved title';
        finish({ title: 'Submitted title', items: [] });
        await pending;
        TestCase.assertEquals('Submitted title', post.dataset.title);
        TestCase.assertTrue(form.isConnected);
        TestCase.assertEquals('New unsaved title', form.elements.title.value);
        const next = FormForm.submit(form);
        finish({ title: 'New unsaved title', items: [] });
        await next;
        TestCase.assertFalse(form.isConnected, 'an unchanged editor closes when saved');
        TestCase.assertEquals('New unsaved title', post.dataset.title);
    } finally { finish?.(null); Api.post = originals.post; Post.fromData = originals.build; form?.remove(); post.remove(); }
};

tests['encrypted sending stays guarded during encryption and cancellation prevents a later send'] = async () => {
    const root = document.createElement('section');
    const unlock = document.createElement('form');
    unlock.className = 'MessageUnlockForm';
    const form = new FormForm().toDOM();
    form.classList.add('MessageComposer');
    for (const [name, value] of [['body', 'A private message'], ['recipientId', '5']]) {
        const input = document.createElement('input');
        input.name = name;
        input.value = value;
        form.append(input);
    }
    root.append(unlock, form);
    document.body.append(root);
    const originals = { encrypt: MessageCrypto.encrypt, threadKey: MessageCrypto.threadKey, fetch: globalThis.fetch };
    let finish, encryptions = 0, sends = 0;
    MessageCrypto.threadKey = () => ({});
    MessageCrypto.encrypt = () => { encryptions++; return new Promise(resolve => { finish = resolve; }); };
    globalThis.fetch = async () => { sends++; throw new Error('cancelled encryption must not send'); };
    try {
        const first = FormForm.submit(form);
        await FormForm.submit(form);
        TestCase.assertEquals(1, encryptions);
        TestCase.assertTrue(FormForm.isPending(form));
        FormForm.cancel(form);
        finish('encrypted-envelope');
        await first;
        TestCase.assertEquals(0, sends);
        TestCase.assertEquals('A private message', form.elements.body.value);
    } finally {
        finish?.(null);
        MessageCrypto.encrypt = originals.encrypt;
        MessageCrypto.threadKey = originals.threadKey;
        globalThis.fetch = originals.fetch;
        root.remove();
    }
};

tests['unlocking stays guarded through key derivation and a cancelled result cannot unlock the thread'] = async () => {
    const form = new FormForm().toDOM();
    form.classList.add('MessageUnlockForm');
    form.dataset.wrappedPrivateKey = '{}';
    form.dataset.otherPublicKey = '{}';
    form.dataset.ownPublicKey = '{}';
    const input = document.createElement('input');
    input.name = 'messagePassphrase';
    input.value = 'a passphrase';
    form.append(input);
    document.body.append(form);
    const originals = { unwrap: MessageCrypto.unwrapPrivateKey, derive: MessageCrypto.conversationKey,
        store: MessageCrypto.storeUnlocked, key: MessageCrypto.threadKey() };
    let derive, unwraps = 0;
    MessageCrypto.unwrapPrivateKey = async () => { unwraps++; return {}; };
    MessageCrypto.conversationKey = () => new Promise(resolve => { derive = resolve; });
    MessageCrypto.storeUnlocked = () => {};
    try {
        const first = FormForm.submit(form);
        await FormForm.submit(form);
        await tick();
        TestCase.assertEquals(1, unwraps);
        TestCase.assertTrue(FormForm.isPending(form));
        FormForm.cancel(form);
        derive({ cancelledKey: true });
        await first;
        TestCase.assertTrue(MessageCrypto.threadKey() === originals.key);
        TestCase.assertFalse(form.hidden);
    } finally {
        derive?.(null);
        MessageCrypto.unwrapPrivateKey = originals.unwrap;
        MessageCrypto.conversationKey = originals.derive;
        MessageCrypto.storeUnlocked = originals.store;
        MessageCrypto.setThreadKey(originals.key);
        form.remove();
    }
};

tests['a cached key from before rotation cannot silently unlock the current account'] = async () => {
    const old = await MessageCrypto.generateKeypair();
    const current = await MessageCrypto.generateKeypair();
    const form = new FormForm().toDOM();
    form.classList.add('MessageUnlockForm');
    form.dataset.ownPublicKey = JSON.stringify(current.publicKey);
    form.dataset.otherPublicKey = '{}';
    document.body.append(form);
    const original = MessageCrypto.conversationKey;
    let derived = false;
    MessageCrypto.conversationKey = async () => { derived = true; return {}; };
    try {
        MessageCrypto.storeUnlocked(old.privateKey);
        MessageUnlockForm.init();
        await tick();
        TestCase.assertFalse(derived, 'the stale private key must be rejected before key derivation');
        TestCase.assertFalse(form.hidden);
        TestCase.assertNull(MessageCrypto.loadUnlocked());
    } finally {
        FormForm.cancel(form);
        MessageCrypto.clearUnlocked();
        MessageCrypto.conversationKey = original;
        form.remove();
    }
};

tests['closing a saving inline editor aborts its request and ignores its late response after reopening'] = async () => {
    const post = document.createElement('article');
    post.className = 'Post';
    post.dataset.postId = '42';
    post.dataset.title = 'Original title';
    post.dataset.descriptionDelta = '{"ops":[]}';
    const button = document.createElement('button');
    post.append(button);
    document.body.append(post);
    const original = globalThis.fetch;
    let finish, signal;
    globalThis.fetch = async (url, options) => {
        signal = options.signal;
        return new Promise(resolve => { finish = resolve; });
    };
    let reopened;
    try {
        PostEditor.open(button);
        const form = post.nextElementSibling;
        const first = FormForm.submit(form);
        TestCase.assertTrue(FormForm.isPending(form));
        form.querySelector('.EditFormCancelButton').click();
        TestCase.assertTrue(signal.aborted);
        PostEditor.open(button);
        reopened = post.nextElementSibling;
        TestCase.assertTrue(reopened !== form);
        finish({ ok: true, json: async () => ({ response: { title: 'Late saved title' } }) });
        await first;
        TestCase.assertEquals('Original title', post.dataset.title);
        TestCase.assertTrue(reopened.isConnected);
        TestCase.assertFalse(FormForm.isPending(reopened));
    } finally {
        finish?.({ ok: false, json: async () => ({}) });
        globalThis.fetch = original;
        reopened?.remove();
        post.nextElementSibling?.classList.contains('PostEditForm') && post.nextElementSibling.remove();
        post.remove();
    }
};

for (const [name, endpoint, fields] of cases) {
    tests[name + ' keeps one request pending and permits retry after a refusal'] = async () => {
        const root = document.createElement('section');
        root.className = 'EncryptedMessagesSetting';
        root.dataset.publicKey = '{}';
        root.dataset.wrappedPrivateKey = '{}';
        const form = new FormForm().toDOM();
        form.classList.add(name);
        for (const name of fields.split(' ').filter(Boolean)) {
            const input = document.createElement(name.endsWith('Text') ? 'textarea' : 'input');
            input.name = name;
            if (['avatar', 'favicon', 'frontPageImage'].includes(name)) {
                input.type = 'file';
                Object.defineProperty(input, 'files', { value: [new window.File(['image'], 'image.png', { type: 'image/png' })] });
            } else {
                input.value = /passphrase/i.test(name) ? 'violet orchard copper 739!' : 'test-value';
                input.checked = true;
            }
            form.append(input);
        }
        const button = document.createElement('button');
        button.type = 'submit';
        button.dataset.action = 'enable';
        form.append(button);
        root.append(form);
        document.body.append(root);

        const originals = { post: Api.post, confirm: Dialog.confirm, formData: globalThis.FormData,
            generate: MessageCrypto.generateKeypair, wrap: MessageCrypto.wrapPrivateKey, unwrap: MessageCrypto.unwrapPrivateKey };
        let finish;
        const wait = new Promise(resolve => { finish = resolve; });
        const calls = [];
        Api.post = async (path, payload, options) => { calls.push({ path, payload, options }); await wait; return null; };
        Dialog.confirm = async () => true;
        globalThis.FormData = window.FormData;
        MessageCrypto.generateKeypair = async () => ({ publicKey: {}, privateKey: {} });
        MessageCrypto.wrapPrivateKey = async () => ({});
        MessageCrypto.unwrapPrivateKey = async () => ({});
        try {
            const first = FormForm.submit(form);
            await tick();
            TestCase.assertEquals(1, calls.length, name + ' reaches its endpoint');
            TestCase.assertEquals('/api/' + endpoint, calls[0].path);
            TestCase.assertTrue(calls[0].options.form === form);
            TestCase.assertNotNull(calls[0].options.signal);
            TestCase.assertTrue(FormForm.isPending(form));
            TestCase.assertTrue(button.disabled && button.classList.contains('Working'));
            await FormForm.submit(form);
            TestCase.assertEquals(1, calls.length);
            finish();
            await first;
            TestCase.assertFalse(FormForm.isPending(form));
            TestCase.assertFalse(button.disabled || button.classList.contains('Working'));
            await FormForm.submit(form);
            TestCase.assertEquals(2, calls.length);
        } finally {
            finish();
            await tick();
            Api.post = originals.post;
            Dialog.confirm = originals.confirm;
            globalThis.FormData = originals.formData;
            MessageCrypto.generateKeypair = originals.generate;
            MessageCrypto.wrapPrivateKey = originals.wrap;
            MessageCrypto.unwrapPrivateKey = originals.unwrap;
            root.remove();
        }
    };
}

tests['confirmation is part of submission so a repeated Enter cannot open another dialog'] = async () => {
    const form = new FormForm().toDOM();
    form.classList.add('AccountDeleteForm');
    let finish, calls = 0;
    const original = Dialog.confirm;
    Dialog.confirm = () => { calls++; return new Promise(resolve => { finish = resolve; }); };
    try {
        const first = FormForm.submit(form);
        await FormForm.submit(form);
        TestCase.assertEquals(1, calls);
        TestCase.assertTrue(FormForm.isPending(form));
        finish(false);
        await first;
        TestCase.assertFalse(FormForm.isPending(form));
    } finally { finish?.(false); Dialog.confirm = original; }
};

tests['login stays guarded until its CAPTCHA is rendered and can then be resubmitted'] = async () => {
    const form = new FormForm().toDOM();
    form.classList.add('LoginForm');
    for (const name of ['identifier', 'password', 'rememberMe']) {
        const input = document.createElement('input');
        input.name = name;
        input.value = 'test-value';
        form.append(input);
    }
    const button = document.createElement('button');
    button.type = 'submit';
    form.append(button);
    const originals = { post: Api.post, recaptcha: window.grecaptcha };
    let renders = 0, resets = 0, calls = 0;
    Api.post = async () => { calls++; return { recaptchaRequired: true, recaptchaSiteKey: 'test-key' }; };
    window.grecaptcha = {
        render: (container, options) => {
            TestCase.assertTrue(FormForm.isPending(form));
            TestCase.assertTrue(button.disabled);
            TestCase.assertTrue(form.contains(container));
            TestCase.assertEquals('test-key', options.sitekey);
            renders++;
            return 7;
        },
        getResponse: () => 'solved-token',
        reset: () => { resets++; },
    };
    try {
        const first = FormForm.submit(form);
        await FormForm.submit(form);
        await first;
        TestCase.assertEquals(1, calls);
        TestCase.assertEquals(1, renders);
        TestCase.assertFalse(button.disabled);
        TestCase.assertEquals(7, form.recaptchaWidgetId);
        await FormForm.submit(form);
        TestCase.assertEquals(2, calls);
        TestCase.assertEquals(1, resets);
        TestCase.assertEquals(1, form.querySelectorAll('.LoginRecaptcha').length);
    } finally { Api.post = originals.post; window.grecaptcha = originals.recaptcha; }
};

tests['recovery-code regeneration submits the clicked action while both buttons are guarded'] = async () => {
    const form = new FormForm().toDOM();
    form.classList.add('TwoFactorSettingsForm');
    const password = document.createElement('input');
    password.name = 'currentPassword';
    password.value = 'test-password';
    const fieldset = document.createElement('fieldset');
    fieldset.append(password);
    form.append(fieldset);
    for (const action of ['disable', 'regenerate-recovery']) {
        const button = document.createElement('button');
        button.type = 'submit';
        button.dataset.action = action;
        form.append(button);
    }
    const original = Api.post;
    const buttons = [...form.querySelectorAll('button')];
    let action;
    Api.post = async (path, payload) => {
        action = payload.action;
        TestCase.assertTrue(buttons.every(button => button.disabled));
        return { recoveryCodes: ['recovery-one', 'recovery-two'] };
    };
    try {
        await FormForm.submit(form, buttons[1]);
        TestCase.assertEquals('regenerate-recovery', action);
        TestCase.assertEquals('disable', buttons[0].dataset.action);
        TestCase.assertEquals('', password.value);
        TestCase.assertNotNull(form.querySelector('.RecoveryCodes'));
    } finally { Api.post = original; }
};

for (const name of ['SetupForm', 'EmailVerifyForm', 'EmailRevertForm', 'EmailDigestResubscribeForm']) {
    tests[name + ' keeps native POST data and releases when the page returns from Back'] = async () => {
        const form = new FormForm().toDOM();
        form.classList.add(name);
        form.action = '/native-confirmation';
        const token = document.createElement('input');
        token.name = 'token';
        token.value = 'confirmation-token';
        form.append(token);
        const original = window.HTMLFormElement.prototype.submit;
        let calls = 0;
        window.HTMLFormElement.prototype.submit = function () {
            calls++;
            TestCase.assertTrue(this === form);
            TestCase.assertEquals('post', this.method);
            TestCase.assertEquals('confirmation-token', new window.FormData(this).get('token'));
        };
        try {
            const first = FormForm.submit(form);
            await FormForm.submit(form);
            TestCase.assertEquals(1, calls);
            TestCase.assertTrue(FormForm.isPending(form));
            window.dispatchEvent(new window.Event('pageshow'));
            await first;
            TestCase.assertFalse(FormForm.isPending(form));
        } finally { FormForm.cancel(form); window.HTMLFormElement.prototype.submit = original; }
    };
}

export default { suite: 'FormControllers', tests };
