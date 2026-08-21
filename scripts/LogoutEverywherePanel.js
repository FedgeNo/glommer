import { Strings } from '/scripts/Strings.js';
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
                Strings.for('ClientStatus').signOutEverywhere || ''
            ))) {
                return;
            }

            button.textContent = Strings.for('ClientStatus').signingOut || '';
            Working.start(button);

            // Api.post answers null rather than throwing, so this is a check
            // and not a catch. It said "Done" and left for the home page on a
            // request that never landed, which is the worst thing this
            // particular button can get wrong: somebody signing every device
            // out is somebody who thinks another person is holding one.
            const signed_out = await Api.post('/api/logout-everywhere', {});

            if (!signed_out) {
                button.textContent = Strings.for('ClientStatus').failed || '';
                Working.stop(button);

                return;
            }

            button.textContent = Strings.for('ClientStatus').done || '';
            window.location.href = '/';
        });
    }
}

ReadyHandler.add(LogoutEverywherePanel.init);
