import { ClientConfig } from '/scripts/ClientConfig.js';
import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class FaviconSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.FaviconSettingsForm');
            if (!form) return;
            event.preventDefault();

            const file_input = form.querySelector('input[type="file"][name="favicon"]');

            if (!file_input.files.length) {
                Toast.show(Strings.for('ClientStatus').chooseFile || '');

                return;
            }

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const body = new FormData();
            body.append('favicon', file_input.files[0]);

            try {
                const data = await Api.post('/api/favicon-settings', body, { form });

                if (!data) return;

                Toast.show(Strings.for('ClientStatus').faviconSaved || '');
                form.querySelector('.FaviconPreview').src = ClientConfig.siteURL() + '/uploads/site/favicon.png?' + Date.now();
            } finally {
                Working.stop(submit_button);
            }
        });
    }
}

ReadyHandler.add(FaviconSettingsForm.init);
