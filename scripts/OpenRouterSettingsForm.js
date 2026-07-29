import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class OpenRouterSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.OpenRouterSettingsForm');

            if (!form) {
                return;
            }

            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');

            if (submit_button) {
                submit_button.disabled = true;
            }

            const data = await Api.post('/api/openrouter-settings', {
                openRouterAPIKey: form.querySelector('[name="openRouterAPIKey"]').value,
                openRouterModel: form.querySelector('[name="openRouterModel"]').value,
                openRouterNeverSpend: form.querySelector('[name="openRouterNeverSpend"]').checked,
            });

            if (submit_button) {
                submit_button.disabled = false;
            }

            if (data !== null) {
                Toast.show('OpenRouter settings saved.');
            }
        });
    }
}

ReadyHandler.add(OpenRouterSettingsForm.init);
