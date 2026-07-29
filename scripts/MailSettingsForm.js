// MailSettingsForm.js
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class MailSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MailSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            const data = await Api.post('/api/mail-settings', {
                smtpHost: form.querySelector('[name="smtpHost"]').value,
                smtpPort: form.querySelector('[name="smtpPort"]').value,
                smtpUsername: form.querySelector('[name="smtpUsername"]').value,
                smtpPassword: form.querySelector('[name="smtpPassword"]').value,
                smtpEncryption: form.querySelector('[name="smtpEncryption"]').value,
            });
            submit_button.disabled = false;
            if (data) Toast.show('Settings saved.');
        });
    }
}

ReadyHandler.add(MailSettingsForm.init);
