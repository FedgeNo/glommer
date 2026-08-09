import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class LogoutForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.LogoutForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

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
                    Working.stop(submit_button);
                    return;
                }

                window.location = ClientConfig.siteURL() + '/';
            } catch (error) {
                Toast.show('Network error. Please check your connection and try again.');
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(LogoutForm.init);
