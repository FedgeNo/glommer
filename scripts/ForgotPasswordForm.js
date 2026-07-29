import { Api } from '/scripts/Api.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class ForgotPasswordForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.ForgotPasswordForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;

            const data = await Api.post('/api/forgot-password', {
                email: form.querySelector('[name="email"]').value,
            });

            submit_button.disabled = false;

            if (!data) return;

            const notice = document.createElement('p');
            notice.textContent = 'If that email address is on file, a password reset link has been sent. If you don\'t see it, check your junk/spam folder.';
            form.replaceWith(notice);
        });
    }
}

ReadyHandler.add(ForgotPasswordForm.init);
