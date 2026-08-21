import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class BotProtectionSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.BotProtectionSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const data = await Api.post('/api/turnstile-settings', {
                turnstileSiteKey: form.querySelector('[name="turnstileSiteKey"]').value,
                turnstileSecretKey: form.querySelector('[name="turnstileSecretKey"]').value,
                recaptchaSiteKey: form.querySelector('[name="recaptchaSiteKey"]').value,
                recaptchaSecretKey: form.querySelector('[name="recaptchaSecretKey"]').value,
            });
            Working.stop(submit_button);
            if (data) Toast.show(Strings.for('ClientStatus').settingsSaved || '');
        });
    }
}

ReadyHandler.add(BotProtectionSettingsForm.init);
