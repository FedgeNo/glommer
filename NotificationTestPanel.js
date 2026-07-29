import { Api } from '/Api.js';
import { ReadyHandler } from '/ReadyHandler.js';

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
            button.disabled = true;

            try {
                await Api.post('/api/send-test-notification', {});
                button.textContent = 'Sent!';
                setTimeout(() => {
                    button.textContent = 'Send test notification';
                    button.disabled = false;
                }, 2000);
            } catch {
                button.textContent = 'Failed';
                button.disabled = false;
            }
        });
    }
}

ReadyHandler.add(NotificationTestPanel.init);

