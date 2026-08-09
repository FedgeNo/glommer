import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class TwoFactorSettingsForm {
    static init() {
        // Word for word what TwoFactorSettingsForm.php renders, so toggling shows
        // the same text a reload would.
        const onExplanation =
            'When you log in, we\'ll email a verification code you have to enter to finish signing in.';
        const offExplanation =
            'Add a second step at login: we\'ll email a verification code you have to enter, so your password alone isn\'t enough to get in.';

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
            });

            if (!data) {
                Working.stop(submit_button);
                return;
            }

            const now_enabled = data.enabled;
            form.querySelector('legend').textContent = now_enabled
                ? 'Two-factor authentication is on'
                : 'Two-factor authentication is off';
            form.querySelector('fieldset p').textContent = now_enabled
                ? onExplanation
                : offExplanation;
            submit_button.textContent = now_enabled
                ? 'Turn off two-factor authentication'
                : 'Turn on two-factor authentication';
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
