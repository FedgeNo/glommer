import { Api } from '/Api.js';
import { Dialog } from '/Dialog.js';
import { ReadyHandler } from '/ReadyHandler.js';

export class LogoutEverywherePanel {
    static init() {
        const panel = document.querySelector('.LogoutEverywherePanel');
        if (!panel) {
            return;
        }

        const button = panel.querySelector('.LogoutEverywhereButton');
        if (!button) {
            return;
        }

        button.addEventListener('click', async (event) => {
            event.preventDefault();

            if (!(await Dialog.confirm(
                'This will sign you out of every device, including this one. Continue?'
            ))) {
                return;
            }

            button.textContent = 'Signing out…';
            button.disabled = true;

            try {
                await Api.post('/api/logout-everywhere', {});
                button.textContent = 'Done';
                window.location.href = '/';
            } catch {
                button.textContent = 'Failed';
                button.disabled = false;
            }
        });
    }
}

ReadyHandler.add(LogoutEverywherePanel.init);
