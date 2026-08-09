import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class EmailChangeForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.EmailChangeForm');
            if (!form) return;
            event.preventDefault();

            const existing_error = form.querySelector('.Error');
            if (existing_error) existing_error.remove();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            try {
                const response = await fetch(ClientConfig.siteURL() + '/api/change-email', {
                    method: 'POST',
                    headers: csrf_headers({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        newEmail: form.querySelector('[name="newEmail"]').value,
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

                if (!data.response.changed) {
                    Toast.show('That is already your email address.');
                    return;
                }

                window.location = ClientConfig.siteURL() + '/check-inbox';
            } catch (error) {
                Toast.show('Network error. Please check your connection and try again.');
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(EmailChangeForm.init);
