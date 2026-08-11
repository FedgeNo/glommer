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

            const submit_button = form.querySelector('button[type="submit"]');
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

            const now_enabled = data.enabled;
            const state = now_enabled ? 'on' : 'off';
            form.querySelector('legend').textContent = pick(words.legend, state);
            form.querySelector('fieldset p').textContent = pick(words.explanation, state);
            submit_button.textContent = pick(words.submit, state);
            submit_button.dataset.action = now_enabled ? 'disable' : 'enable';
            password_input.value = '';
            Working.stop(submit_button);

            Toast.show(
                now_enabled
                    ? 'Two-factor authentication is now on.'
                    : 'Two-factor authentication is now off.'
            );
        });
    }
}

ReadyHandler.add(TwoFactorSettingsForm.init);
