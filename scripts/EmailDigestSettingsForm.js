import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Client twin of EmailDigestSettingsForm.php: saves the paragraph this server
 * adds to every digest.
 */
export class EmailDigestSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.EmailDigestSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            const field = form.querySelector('textarea');
            const data = await Api.post('/api/email-digest-settings', { [field.name]: field.value });
            submit_button.disabled = false;
            if (data) Toast.show('Settings saved.');
        });
    }
}

ReadyHandler.add(EmailDigestSettingsForm.init);
