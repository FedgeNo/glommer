// TwoFactorForm.js
import { Api } from '/scripts/Api.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class TwoFactorForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.TwoFactorForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/verify-2fa', {
                code: form.querySelector('[name="code"]').value,
            });
            if (!data) {
                Working.stop(submit_button);
                return;
            }
            window.location = ClientConfig.siteURL() + '/';
        });
    }
}

ReadyHandler.add(TwoFactorForm.init);
