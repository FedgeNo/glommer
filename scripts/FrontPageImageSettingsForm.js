import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

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
            Working.start(submit_button);

            const body = new FormData();
            body.append('frontPageImage', file_input.files[0]);

            try {
                const data = await Api.post('/api/front-page-image', body, { form });

                if (!data) return;

                Toast.show('Settings saved.');

                // First upload has no preview element yet; a reload-free page
                // gets one the next time the form renders, and the cache-bust
                // keeps an existing one honest.
                const preview = form.querySelector('.FrontPageImagePreview');

                if (preview) {
                    preview.src = data.url + '?' + Date.now();
                }
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(FrontPageImageSettingsForm.init);
