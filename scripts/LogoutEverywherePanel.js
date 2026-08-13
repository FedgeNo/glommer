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

            // Api.post answers null rather than throwing, so this is a check
            // and not a catch. It said "Done" and left for the home page on a
            // request that never landed, which is the worst thing this
            // particular button can get wrong: somebody signing every device
            // out is somebody who thinks another person is holding one.
            const signed_out = await Api.post('/api/logout-everywhere', {});

            if (!signed_out) {
                button.textContent = 'Failed';
                Working.stop(button);

                return;
            }

            button.textContent = 'Done';
            window.location.href = '/';
        });
    }
}

ReadyHandler.add(LogoutEverywherePanel.init);
