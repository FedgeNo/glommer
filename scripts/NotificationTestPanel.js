import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Strings } from '/scripts/Strings.js';
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

        // The button's own resting label lives in NotificationTestPanel.php;
        // read here too so the three states this method cycles it through
        // stay in the same language rather than falling back to English
        // partway through.
        const words = Strings.for('NotificationTestPanel', {
            button: 'Send test notification',
            sending: 'Sending…',
            sent: 'Sent!',
            failed: 'Failed',
        });

        button.addEventListener('click', async (event) => {
            event.preventDefault();
            button.textContent = words.sending;
            Working.start(button);

            try {
                await Api.post('/api/send-test-notification', {});
                button.textContent = words.sent;
                setTimeout(() => {
                    button.textContent = words.button;
                    Working.stop(button);
                }, 2000);
            } catch {
                button.textContent = words.failed;
                Working.stop(button);
            }
        });
    }
}

ReadyHandler.add(NotificationTestPanel.init);

