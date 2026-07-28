// TwoFactorForm.js
import { Api } from '/Api.js';
import { ClientConfig } from '/ClientConfig.js';
import { ReadyHandler } from '/ReadyHandler.js';

export class TwoFactorForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.TwoFactorForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            const data = await Api.post('/api/verify-2fa', {
                code: form.querySelector('[name="code"]').value,
            });
            if (!data) {
                submit_button.disabled = false;
                return;
            }
            window.location = ClientConfig.siteURL() + '/';
        });
    }
}

ReadyHandler.add(TwoFactorForm.init);
