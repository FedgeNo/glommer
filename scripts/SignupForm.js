// SignupForm.js
import { Api } from '/scripts/Api.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class SignupForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.SignupForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);
            const captcha_input = form.querySelector('[name="cf-turnstile-response"]');
            const data = await Api.post('/api/signup', {
                username: form.querySelector('[name="username"]').value,
                email: form.querySelector('[name="email"]').value,
                displayName: form.querySelector('[name="displayName"]').value,
                description: form.querySelector('[name="description"]').value,
                password: form.querySelector('[name="password"]').value,
                rememberMe: form.querySelector('[name="rememberMe"]').checked,
                captchaToken: captcha_input ? captcha_input.value : null,
            }, { form });
            if (!data) {
                Working.stop(submit_button);
                return;
            }
            window.location = ClientConfig.siteURL() + (data.verified ? '/' : '/check-inbox');
        });
    }
}

ReadyHandler.add(SignupForm.init);
