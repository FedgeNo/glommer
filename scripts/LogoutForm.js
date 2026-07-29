import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class LogoutForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.LogoutForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;

            try {
                const response = await fetch(ClientConfig.siteURL() + '/api/logout', {
                    method: 'POST',
                    body: new FormData(form),
                });

                if (!response.ok) {
                    let errorMsg = 'Logout failed. Please try again.';
                    try {
                        const data = await response.json();
                        errorMsg = data.error || errorMsg;
                    } catch (_) {}
                    Toast.show(errorMsg);
                    submit_button.disabled = false;
                    return;
                }

                window.location = ClientConfig.siteURL() + '/';
            } catch (error) {
                Toast.show('Network error. Please check your connection and try again.');
                submit_button.disabled = false;
            }
        });
    }
}

ReadyHandler.add(LogoutForm.init);
