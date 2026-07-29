import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class GoogleAuthSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.GoogleAuthSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            const data = await Api.post('/api/google-auth-settings', {
                googleAuthClientId: form.querySelector('[name="googleAuthClientId"]').value,
                googleAuthSecret: form.querySelector('[name="googleAuthSecret"]').value,
            });
            submit_button.disabled = false;
            if (data) Toast.show('Settings saved.');
        });
    }
}

ReadyHandler.add(GoogleAuthSettingsForm.init);
