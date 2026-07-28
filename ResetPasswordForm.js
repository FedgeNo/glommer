import { Api } from '/Api.js';
import { Toast } from '/Toast.js';
import { ClientConfig } from '/ClientConfig.js';
import { ReadyHandler } from '/ReadyHandler.js';

export class ResetPasswordForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.ResetPasswordForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;

            const data = await Api.post('/api/reset-password', {
                token: form.querySelector('[name="token"]').value,
                newPassword: form.querySelector('[name="newPassword"]').value,
                confirmPassword: form.querySelector('[name="confirmPassword"]').value,
            });

            if (!data) {
                submit_button.disabled = false;
                return;
            }

            if (!data.reset) {
                submit_button.disabled = false;
                Toast.show('That\'s already your password - nothing was changed.');
                return;
            }

            const notice = document.createElement('p');
            notice.textContent = 'Your password has been reset. You can now log in.';

            const login_link = document.createElement('a');
            login_link.href = ClientConfig.siteURL() + '/login';
            login_link.textContent = 'Log In';

            form.replaceWith(notice, login_link);
        });
    }
}

ReadyHandler.add(ResetPasswordForm.init);
