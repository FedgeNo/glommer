import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Client twin of SensitiveMediaSetting.php: saves the reader's answer as they
 * give it.
 *
 * The box is left showing what they chose even while the request is in flight -
 * a checkbox that springs back and then settles reads as a fault. If the save
 * fails, Api has already told them so.
 */
export class SensitiveMediaSetting {
    static init() {
        document.addEventListener('change', async (event) => {
            const input = event.target.closest('.SensitiveMediaSetting input[name="showSensitiveMedia"]');

            if (!input) return;

            await Api.post('/api/update-sensitive-media', { showSensitiveMedia: input.checked });
        });
    }
}

ReadyHandler.add(SensitiveMediaSetting.init);
