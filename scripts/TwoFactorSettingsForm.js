import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';
import { Strings } from '/scripts/Strings.js';

export class TwoFactorSettingsForm {
    static init() {
        // Read from the same table TwoFactorSettingsForm.php renders from, so
        // toggling shows the same words a reload would - in whatever language
        // that is, not just English.
        const words = Strings.for('TwoFactorSettingsForm');
        const pick = (entry, state) => (entry || {})[state] || '';

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.TwoFactorSettingsForm');
            if (!form) return;
            event.preventDefault();

            const existing_error = form.querySelector('.Error');
            if (existing_error) existing_error.remove();

            // The form carries two submit buttons while 2FA is on (the toggle
            // and the recovery-code regenerate), so the clicked one decides
            // the action.
            const toggle_button = form.querySelector('button[type="submit"]');
            const submit_button = event.submitter && event.submitter.dataset.action
                ? event.submitter
                : toggle_button;
            const password_input = form.querySelector('[name="currentPassword"]');
            Working.start(submit_button);

            const data = await Api.post('/api/two-factor', {
                action: submit_button.dataset.action,
                currentPassword: password_input.value,
            }, { form });

            if (!data) {
                Working.stop(submit_button);
                return;
            }

            password_input.value = '';

            if (data.recoveryCodes) {
                TwoFactorSettingsForm.#showRecoveryCodes(form, data.recoveryCodes, words);
            }

            if (submit_button.dataset.action === 'regenerate-recovery') {
                Working.stop(submit_button);
                Toast.show(pick(words.toast, 'regenerated'));
                return;
            }

            const now_enabled = data.enabled;
            const state = now_enabled ? 'on' : 'off';
            form.querySelector('legend').textContent = pick(words.legend, state);
            form.querySelector('fieldset p').textContent = pick(words.explanation, state);
            toggle_button.textContent = pick(words.submit, state);
            toggle_button.dataset.action = now_enabled ? 'disable' : 'enable';

            // The regenerate button and any shown codes exist only while 2FA
            // is on, mirroring what a reload would render.
            const regenerate_button = form.querySelector('[data-action="regenerate-recovery"]');

            if (now_enabled && !regenerate_button) {
                const regenerate = document.createElement('button');
                regenerate.className = 'Button SubmitButton';
                regenerate.type = 'submit';
                regenerate.dataset.action = 'regenerate-recovery';
                regenerate.textContent = words.regenerate || '';
                form.appendWithSpace(regenerate);
            } else if (!now_enabled) {
                if (regenerate_button) regenerate_button.remove();
                const codes_block = form.querySelector('.RecoveryCodes');
                if (codes_block) codes_block.remove();
            }

            Working.stop(submit_button);

            Toast.show(pick(words.toast, state));
        });
    }

    // The one time the codes exist in plain text is the response that issued
    // them, so this is the one place they're ever shown.
    static #showRecoveryCodes(form, codes, words) {
        const existing = form.querySelector('.RecoveryCodes');
        if (existing) existing.remove();

        const block = document.createElement('div');
        block.className = 'RecoveryCodes';

        const heading = document.createElement('p');
        heading.textContent = words.recoveryHeading || '';
        block.appendWithSpace(heading);

        const note = document.createElement('p');
        note.textContent = words.recoveryNote || '';
        block.appendWithSpace(note);

        const list = document.createElement('ul');

        for (const code of codes) {
            const item = document.createElement('li');
            const code_element = document.createElement('code');
            code_element.textContent = code;
            item.appendWithSpace(code_element);
            list.appendWithSpace(item);
        }

        block.appendWithSpace(list);
        form.querySelector('fieldset').after(block);
    }
}

ReadyHandler.add(TwoFactorSettingsForm.init);
