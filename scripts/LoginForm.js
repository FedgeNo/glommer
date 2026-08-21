import { ClientConfig } from '/scripts/ClientConfig.js';
import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class LoginForm {
    static #recaptchaLoading = null;

    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.LoginForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const captcha_input = form.querySelector('[name="cf-turnstile-response"]');
            const recaptcha_token = form.recaptchaWidgetId !== undefined && window.grecaptcha
                ? window.grecaptcha.getResponse(form.recaptchaWidgetId)
                : null;

            const data = await Api.post('/api/login', {
                identifier: form.querySelector('[name="identifier"]').value,
                password: form.querySelector('[name="password"]').value,
                rememberMe: form.querySelector('[name="rememberMe"]').checked,
                captchaToken: captcha_input ? captcha_input.value : null,
                recaptchaToken: recaptcha_token || null,
            }, { form });

            if (!data) {
                LoginForm.#resetRecaptcha(form);
                Working.stop(submit_button);
                return;
            }

            if (data.recaptchaRequired) {
                LoginForm.#showRecaptcha(form, data.recaptchaSiteKey);
                Working.stop(submit_button);
                return;
            }

            if (data.twoFactorRequired) {
                window.location = ClientConfig.siteURL() + '/login';
                return;
            }

            window.location = ClientConfig.siteURL() + '/';
        });
    }

    // --- reCAPTCHA helpers (moved from main.js) ---

    static #loadRecaptchaApi() {
        if (window.grecaptcha && window.grecaptcha.render) return Promise.resolve();
        if (LoginForm.#recaptchaLoading) return LoginForm.#recaptchaLoading;

        LoginForm.#recaptchaLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://www.google.com/recaptcha/api.js?render=explicit';
            script.async = true;
            script.defer = true;
            script.addEventListener('load', () => {
                const wait = () => {
                    if (window.grecaptcha && window.grecaptcha.render) resolve();
                    else setTimeout(wait, 50);
                };
                wait();
            });
            script.addEventListener('error', () => reject(new Error('reCAPTCHA failed to load')));
            document.head.appendWithSpace(script);
        });

        return LoginForm.#recaptchaLoading;
    }

    static async #showRecaptcha(form, site_key) {
        if (form.recaptchaWidgetId !== undefined) {
            window.grecaptcha.reset(form.recaptchaWidgetId);
            return;
        }

        const notice = document.createElement('p');
        notice.className = 'LoginRecaptchaNotice';
        notice.textContent = Strings.for('LoginClient').verificationRequired || '';

        const container = document.createElement('div');
        container.className = 'LoginRecaptcha';

        const submit_button = form.querySelector('button[type="submit"]');
        form.insertBeforeWithSpace(notice, submit_button);
        form.insertBeforeWithSpace(container, submit_button);

        try {
            await LoginForm.#loadRecaptchaApi();
            form.recaptchaWidgetId = window.grecaptcha.render(container, { sitekey: site_key });
        } catch (error) {
            Toast.show(Strings.for('LoginClient').verificationFailed || '');
        }
    }

    static #resetRecaptcha(form) {
        if (form.recaptchaWidgetId !== undefined && window.grecaptcha) {
            window.grecaptcha.reset(form.recaptchaWidgetId);
        }
    }
}

ReadyHandler.add(LoginForm.init);
