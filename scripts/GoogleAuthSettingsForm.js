import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class GoogleAuthSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.GoogleAuthSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/google-auth-settings', {
                googleAuthClientId: form.querySelector('[name="googleAuthClientId"]').value,
                googleAuthSecret: form.querySelector('[name="googleAuthSecret"]').value,
            });
            Working.stop(submit_button);
            if (data) Toast.show(Strings.for('ClientStatus').settingsSaved || '');
        });
    }
}

ReadyHandler.add(GoogleAuthSettingsForm.init);
