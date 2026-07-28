import { Api } from '/Api.js';
import { Toast } from '/Toast.js';
import { ReadyHandler } from '/ReadyHandler.js';

export class BotProtectionSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.BotProtectionSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            const data = await Api.post('/api/turnstile-settings', {
                turnstileSiteKey: form.querySelector('[name="turnstileSiteKey"]').value,
                turnstileSecretKey: form.querySelector('[name="turnstileSecretKey"]').value,
                recaptchaSiteKey: form.querySelector('[name="recaptchaSiteKey"]').value,
                recaptchaSecretKey: form.querySelector('[name="recaptchaSecretKey"]').value,
            });
            submit_button.disabled = false;
            if (data) Toast.show('Settings saved.');
        });
    }
}

ReadyHandler.add(BotProtectionSettingsForm.init);
