import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { Dialog } from '/scripts/Dialog.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class AccountDeleteForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.AccountDeleteForm');
            if (!form) return;
            event.preventDefault();

            if (!await Dialog.confirm('Delete your account? Your posts, replies, and messages are gone permanently - this can\'t be undone.')) return;

            const existing_error = form.querySelector('.Error');
            if (existing_error) existing_error.remove();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const response = await fetch(ClientConfig.siteURL() + '/api/delete-account', {
                    method: 'POST',
                    headers: csrf_headers({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        currentPassword: form.querySelector('[name="currentPassword"]').value,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    const error = document.createElement('p');
                    error.className = 'Error';
                    error.textContent = data.error;
                    form.insertBeforeWithSpace(error, submit_button);
                    return;
                }

                window.location = ClientConfig.siteURL() + '/';
            } catch (error) {
                Toast.show('Network error. Please check your connection and try again.');
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(AccountDeleteForm.init);
