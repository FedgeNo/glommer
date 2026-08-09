import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class MapSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MapSettingsForm');

            if (!form) {
                return;
            }

            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');

            if (submit_button) {
                Working.start(submit_button);
            }

            const data = await Api.post('/api/map-settings', {
                mapTileURL: form.querySelector('[name="mapTileURL"]').value,
                mapTileAPIKey: form.querySelector('[name="mapTileAPIKey"]').value,
                mapTileAttribution: form.querySelector('[name="mapTileAttribution"]').value,
            });

            if (submit_button) {
                Working.stop(submit_button);
            }

            if (data !== null) {
                Toast.show('Map settings saved.');
            }
        });
    }
}

ReadyHandler.add(MapSettingsForm.init);
