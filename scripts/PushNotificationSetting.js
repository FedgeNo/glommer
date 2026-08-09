// PushNotificationSetting.js
import { Api } from '/scripts/Api.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

/**
 * The one button that turns browser push on or off for this device. Whether
 * it is currently on is the browser's own truth (its PushManager
 * subscription), so the button reads that first and mirrors it, and every
 * change is made to the browser and then reported to the server.
 */
export class PushNotificationSetting {
    static async init() {
        const button = document.querySelector('.PushSubscribeButton');
        if (!button) return;

        // A browser without service workers or push simply can't offer this.
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            // Nothing to wait for - this browser will never offer it.
            button.disabled = true;
            button.textContent = 'Push isn\'t supported in this browser';
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const existing = await registration.pushManager.getSubscription();

        PushNotificationSetting.#reflect(button, existing !== null);

        button.addEventListener('click', async () => {
            Working.start(button);

            try {
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    await PushNotificationSetting.#disable(subscription);
                    PushNotificationSetting.#reflect(button, false);
                } else {
                    const made = await PushNotificationSetting.#enable(registration);
                    PushNotificationSetting.#reflect(button, made);
                }
            } finally {
                Working.stop(button);
            }
        });
    }

    static #reflect(button, subscribed) {
        button.textContent = subscribed ? 'Turn off on this device' : 'Enable on this device';
        button.classList.toggle('Removing', subscribed);
    }

    static async #enable(registration) {
        if (Notification.permission === 'denied') {
            Toast.show('Notifications are blocked for this site in your browser settings.');
            return false;
        }

        const applicationServerKey = ClientConfig.get('vapidPublicKey');
        if (!applicationServerKey) {
            Toast.show('Push isn\'t available on this server.');
            return false;
        }

        let subscription;
        try {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey,
            });
        } catch (_) {
            Toast.show('Could not enable notifications.');
            return false;
        }

        const json = subscription.toJSON();
        const result = await Api.post('/api/push-subscribe', {
            endpoint: subscription.endpoint,
            p256dh: json.keys.p256dh,
            auth: json.keys.auth,
        });

        if (!result) {
            // The server wouldn't record it, so don't leave the browser
            // holding a subscription nothing will ever send to.
            await subscription.unsubscribe();
            return false;
        }

        Toast.show('Notifications on for this device.');
        return true;
    }

    static async #disable(subscription) {
        await Api.post('/api/push-unsubscribe', { endpoint: subscription.endpoint });
        await subscription.unsubscribe();
        Toast.show('Notifications off for this device.');
    }
}

ReadyHandler.add(PushNotificationSetting.init);
