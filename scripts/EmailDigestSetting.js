import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Client twin of EmailDigestSetting.php: saves the answer as it is given.
 *
 * The box is left showing what they chose even while the request is in flight -
 * a checkbox that springs back and then settles reads as a fault. If the save
 * fails, Api has already told them so.
 */
export class EmailDigestSetting {
    static init() {
        document.addEventListener('change', async (event) => {
            const input = event.target.closest('.EmailDigestSetting input[name="emailDigests"]');

            if (!input) return;

            await Api.post('/api/update-email-digests', { emailDigests: input.checked });
        });
    }
}

ReadyHandler.add(EmailDigestSetting.init);
