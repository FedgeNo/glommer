import { TestCase } from './TestCase.js';
import { Api, ClientConfig, Toast } from '../../scripts/Runtime.js';
import { FormForm } from '../../scripts/HTMLObjects.js';
import { PushNotificationSetting } from '../../scripts/Controllers.js';

async function fixture(work, { serverSubscribed = false, browserSubscribed = true } = {}) {
    const saved = {
        post: Api.post, config: ClientConfig.get, toast: Toast.show,
        worker: Object.getOwnPropertyDescriptor(navigator, 'serviceWorker'),
        push: Object.getOwnPropertyDescriptor(window, 'PushManager'),
        notification: Object.getOwnPropertyDescriptor(window, 'Notification'),
    };
    const form = new FormForm().toDOM();
    form.classList.add('PushNotificationSetting');
    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'PushSubscribeButton';
    const list = document.createElement('div');
    list.className = 'PushSubscriptionList';
    form.append(button, list);
    document.body.append(form);
    let unsubscribes = 0;
    let subscription;
    const browserSubscription = {
        endpoint: 'https://push.example.com/private-endpoint',
        toJSON: () => ({ keys: { p256dh: 'browser-key', auth: 'browser-auth' } }),
        async unsubscribe() { unsubscribes++; subscription = null; return true; },
    };
    subscription = browserSubscribed ? browserSubscription : null;
    const registration = { pushManager: {
        async getSubscription() { return subscription; },
        async subscribe() { subscription = browserSubscription; return subscription; },
    } };
    Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: {
        getRegistration: async () => registration, ready: Promise.resolve(registration),
    } });
    Object.defineProperty(window, 'PushManager', { configurable: true, value: class {} });
    Object.defineProperty(window, 'Notification', { configurable: true, value: { permission: 'granted' } });
    ClientConfig.get = key => key === 'vapidPublicKey' ? 'test-vapid' : saved.config.call(ClientConfig, key);
    Toast.show = () => {};
    const records = [
        { id: 101, label: '<img src=x onerror=alert(1)>', createdAt: '2026-09-01 00:00:00', current: serverSubscribed },
        { id: 102, label: 'Old browser', createdAt: '2026-09-02 00:00:00', current: false },
    ];
    Api.post = async () => ({ subscriptions: records, subscribed: serverSubscribed });
    try {
        await PushNotificationSetting.init();
        await work({ form, button, list, records, unsubscribes: () => unsubscribes });
    } finally {
        form.remove();
        Api.post = saved.post;
        ClientConfig.get = saved.config;
        Toast.show = saved.toast;
        for (const [target, key, descriptor] of [
            [navigator, 'serviceWorker', saved.worker], [window, 'PushManager', saved.push], [window, 'Notification', saved.notification],
        ]) {
            if (descriptor) Object.defineProperty(target, key, descriptor);
            else delete target[key];
        }
    }
}

export default {
    suite: 'PushNotificationSetting',
    tests: {
        async 'server removal is reflected even when the browser still holds its subscription'() {
            await fixture(async ({ form, button, list, records }) => {
                TestCase.assertEquals('false', button.dataset.subscribed);
                TestCase.assertEquals(0, list.querySelectorAll('img').length);
                let registered = false;
                Api.post = async (url, payload, options) => {
                    TestCase.assertEquals('/api/push-subscribe', url);
                    TestCase.assertEquals('browser-key', payload.p256dh);
                    TestCase.assertTrue(Boolean(options.signal));
                    registered = true;
                    return { subscriptions: records, subscribed: true };
                };
                await FormForm.submit(form, button);
                TestCase.assertTrue(registered);
                TestCase.assertEquals('true', button.dataset.subscribed);
            });
        },
        async 'failed registration preserves an already existing browser channel'() {
            await fixture(async ({ form, button, unsubscribes }) => {
                Api.post = async () => null;
                await FormForm.submit(form, button);
                TestCase.assertEquals(0, unsubscribes());
                TestCase.assertEquals('false', button.dataset.subscribed);
            });
        },
        async 'failed registration cleans up a newly created browser channel'() {
            await fixture(async ({ form, button, unsubscribes }) => {
                Api.post = async () => null;
                await FormForm.submit(form, button);
                TestCase.assertEquals(1, unsubscribes());
            }, { browserSubscribed: false });
        },
        async 'removing an old destination shares the form guard and does not disable this browser'() {
            await fixture(async ({ form, button, records, unsubscribes }) => {
                const remove = form.querySelector('[data-subscription-id="102"]');
                let complete;
                let calls = 0;
                Api.post = (url, payload) => {
                    calls++;
                    TestCase.assertEquals('/api/push-unsubscribe', url);
                    TestCase.assertEquals(102, payload.subscriptionId);
                    return new Promise(resolve => { complete = resolve; });
                };
                const pending = FormForm.submit(form, remove);
                await FormForm.submit(form, button);
                TestCase.assertEquals(1, calls);
                complete({ subscriptions: [records[0]], subscribed: true });
                await pending;
                TestCase.assertEquals(0, unsubscribes());
                TestCase.assertEquals('true', button.dataset.subscribed);
                TestCase.assertEquals(1, form.querySelectorAll('.PushSubscription').length);
            }, { serverSubscribed: true });
        },
    },
};
