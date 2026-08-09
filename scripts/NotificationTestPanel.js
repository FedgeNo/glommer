import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

export class NotificationTestPanel {
    static init() {
        const panel = document.querySelector('.NotificationTestPanel');
        if (!panel) {
            return;
        }

        const button = panel.querySelector('button');
        if (!button) {
            return;
        }

        button.addEventListener('click', async (event) => {
            event.preventDefault();
            button.textContent = 'Sending…';
            Working.start(button);

            try {
                await Api.post('/api/send-test-notification', {});
                button.textContent = 'Sent!';
                setTimeout(() => {
                    button.textContent = 'Send test notification';
                    Working.stop(button);
                }, 2000);
            } catch {
                button.textContent = 'Failed';
                Working.stop(button);
            }
        });
    }
}

ReadyHandler.add(NotificationTestPanel.init);

