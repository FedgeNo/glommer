// FaviconSettingsForm.js
import { ClientConfig } from '/ClientConfig.js';
import { Toast } from '/Toast.js';
import { csrf_headers } from '/utils.js';
import { ReadyHandler } from '/ReadyHandler.js';

export class FaviconSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.FaviconSettingsForm');
            if (!form) return;
            event.preventDefault();
            const file_input = form.querySelector('input[type="file"][name="favicon"]');
            if (!file_input.files.length) {
                Toast.show('Choose a file first.');
                return;
            }
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            const body = new FormData();
            body.append('favicon', file_input.files[0]);
            try {
                const response = await fetch(ClientConfig.siteURL() + '/api/favicon-settings', {
                    method: 'POST',
                    headers: csrf_headers(),
                    body,
                });
                const data = await response.json();
                if (!response.ok) {
                    Toast.show(data.error || 'Something went wrong. Please try again.');
                    return;
                }
                Toast.show('Settings saved.');
                form.querySelector('.FaviconPreview').src = ClientConfig.siteURL() + '/uploads/site/favicon.png?' + Date.now();
            } catch (error) {
                Toast.show('Network error. Please check your connection and try again.');
            } finally {
                submit_button.disabled = false;
            }
        });
    }
}

ReadyHandler.add(FaviconSettingsForm.init);
