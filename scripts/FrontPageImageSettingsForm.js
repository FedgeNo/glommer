// FrontPageImageSettingsForm.js
import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class FrontPageImageSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.FrontPageImageSettingsForm');
            if (!form) return;
            event.preventDefault();

            const file_input = form.querySelector('input[type="file"][name="frontPageImage"]');
            if (!file_input.files.length) {
                Toast.show('Choose a file first.');
                return;
            }

            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;

            const body = new FormData();
            body.append('frontPageImage', file_input.files[0]);

            try {
                const response = await fetch(ClientConfig.siteURL() + '/api/front-page-image', {
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

                // First upload has no preview element yet; a reload-free page
                // gets one the next time the form renders, and the cache-bust
                // keeps an existing one honest.
                const preview = form.querySelector('.FrontPageImagePreview');
                if (preview) {
                    preview.src = data.response.url + '?' + Date.now();
                }
            } catch (error) {
                Toast.show('Network error. Please check your connection and try again.');
            } finally {
                submit_button.disabled = false;
            }
        });
    }
}

ReadyHandler.add(FrontPageImageSettingsForm.init);
