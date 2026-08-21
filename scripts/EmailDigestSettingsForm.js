import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

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
            Working.start(submit_button);
            const field = form.querySelector('textarea');
            const data = await Api.post('/api/email-digest-settings', { [field.name]: field.value });
            Working.stop(submit_button);
            if (data) Toast.show(Strings.for('ClientStatus').settingsSaved || '');
        });
    }
}

ReadyHandler.add(EmailDigestSettingsForm.init);
