import { Api } from '/Api.js';
import { Toast } from '/Toast.js';
import { ReadyHandler } from '/ReadyHandler.js';

export class SiteInfoSettingsForm {
    static init() {
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.SiteInfoSettingsForm');
            if (!form) return;
            event.preventDefault();
            const submit_button = form.querySelector('button[type="submit"]');
            submit_button.disabled = true;
            const field = form.querySelector('textarea');
            const field_name = field.name;
            const path = '/api/' + field_name.replace(/Text$/, '') + '-settings';
            const data = await Api.post(path, { [field_name]: field.value });
            submit_button.disabled = false;
            if (data) Toast.show('Settings saved.');
        });
    }
}

ReadyHandler.add(SiteInfoSettingsForm.init);

