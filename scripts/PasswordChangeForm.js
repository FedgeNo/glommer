import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class PasswordChangeForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.PasswordChangeForm');
            if (!form) return;
            event.preventDefault();

            const existing_error = form.querySelector('.Error');
            if (existing_error) existing_error.remove();

            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;

            try {
                const response = await fetch(ClientConfig.siteURL() + '/api/change-password', {
                    method: 'POST',
                    headers: csrf_headers({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        currentPassword: form.querySelector('[name="currentPassword"]').value,
                        newPassword: form.querySelector('[name="newPassword"]').value,
                        confirmPassword: form.querySelector('[name="confirmPassword"]').value,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    Toast.show(data.error);
                    return;
                }

                form.reset();
                Toast.show('Password changed!');
            } catch (error) {
                Toast.show('Network error. Please check your connection and try again.');
            } finally {
                submit_button.disabled = false;
            }
        });
    }
}

ReadyHandler.add(PasswordChangeForm.init);
