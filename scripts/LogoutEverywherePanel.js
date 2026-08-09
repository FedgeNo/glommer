import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

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
            Working.start(button);

            try {
                await Api.post('/api/logout-everywhere', {});
                button.textContent = 'Done';
                window.location.href = '/';
            } catch {
                button.textContent = 'Failed';
                Working.stop(button);
            }
        });
    }
}

ReadyHandler.add(LogoutEverywherePanel.init);
