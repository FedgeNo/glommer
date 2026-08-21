import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { Toast } from '/scripts/Toast.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class PasswordResetForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.PasswordResetForm');
            if (!form) return;
            event.preventDefault();

            const submit_button = form.querySelector('button[type="submit"]');
            Working.start(submit_button);

            const data = await Api.post('/api/reset-password', {
                token: form.querySelector('[name="token"]').value,
                newPassword: form.querySelector('[name="newPassword"]').value,
                confirmPassword: form.querySelector('[name="confirmPassword"]').value,
            }, { form });

            if (!data) {
                Working.stop(submit_button);
                return;
            }

            if (!data.reset) {
                Working.stop(submit_button);
                Toast.show(Strings.for('ClientStatus').passwordUnchanged || '');
                return;
            }

            const notice = document.createElement('p');
            const words = Strings.for('ClientStatus');
            notice.textContent = words.passwordReset || '';

            const login_link = document.createElement('a');
            login_link.href = ClientConfig.siteURL() + '/login';
            login_link.textContent = words.login || '';

            form.replaceWith(notice, login_link);
        });
    }
}

ReadyHandler.add(PasswordResetForm.init);
