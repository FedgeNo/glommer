import { Strings } from '/scripts/Strings.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

/**
 * Closing your own account, which asks for the password first.
 *
 * Api is handed the form, so a wrong password is said under the password box
 * rather than as a loose line above the button.
 */
export class AccountDeleteForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.AccountDeleteForm');
            if (!form) return;
            event.preventDefault();

            if (!await Dialog.confirm(Strings.for('ClientStatus').deleteAccount || '')) return;

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const data = await Api.post('/api/delete-account', {
                    currentPassword: form.querySelector('[name="currentPassword"]').value,
                }, { form });

                if (!data) return;

                window.location = ClientConfig.siteURL() + '/';
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(AccountDeleteForm.init);
