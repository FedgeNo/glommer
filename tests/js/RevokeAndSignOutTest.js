import { TestCase } from './TestCase.js';
import { Dialog } from '../../scripts/Dialog.js';
import { RememberedDevice } from '../../scripts/RememberedDevice.js';
import { LogoutEverywherePanel } from '../../scripts/LogoutEverywherePanel.js';

/**
 * The two buttons that end a session somewhere else, and what they say when
 * the request does not land.
 *
 * Api.post answers null rather than throwing, so both of these were wrapped in
 * a catch that could never run: one took the device's card away and the other
 * said "Done" and left for the home page, on a request the server had refused.
 * Both are pressed by somebody who thinks another person is holding one of
 * their logins, which makes a false yes the worst answer either can give.
 */
async function withFetch(ok, body) {
    const real_fetch = globalThis.fetch;
    const real_confirm = Dialog.confirm;
    const posted = [];

    // The animation asks whether the reader wants motion; jsdom has no answer.
    window.matchMedia ??= () => ({ matches: false, addEventListener() {}, removeEventListener() {} });

    globalThis.fetch = async (url, options) => {
        posted.push(String(url));

        return ok
            ? { ok: true, status: 200, json: async () => ({ response: { revoked: true } }) }
            : { ok: false, status: 500, json: async () => ({ error: 'nope' }) };
    };

    Dialog.confirm = async () => true;

    try {
        await body(posted);
    } finally {
        globalThis.fetch = real_fetch;
        Dialog.confirm = real_confirm;
    }
}

function deviceCard() {
    const card = document.createElement('div');
    card.className = 'RememberedDevice';

    const button = document.createElement('button');
    button.className = 'RememberedDeviceRevokeButton';
    button.dataset.tokenId = '7';
    card.appendChild(button);

    document.body.appendChild(card);

    return { card, button };
}

const settle = () => new Promise(resolve => setTimeout(resolve, 0));

export default {
    suite: 'Revoking a session',
    tests: {
        async 'a device whose revocation failed is still shown'() {
            await withFetch(false, async (posted) => {
                const { card, button } = deviceCard();

                button.click();
                await settle();
                await settle();
                await settle();

                TestCase.assertEquals(1, posted.length, 'the request was made');
                TestCase.assertEquals('Failed', button.textContent);
                TestCase.assertNotNull(card.parentNode, 'the device is still listed');

                card.remove();
            });
        },

        async 'a device that was revoked is taken off the list'() {
            await withFetch(true, async () => {
                const { card, button } = deviceCard();

                button.click();
                await settle();
                await settle();

                TestCase.assertFalse(button.textContent === 'Failed', 'nothing went wrong');

                card.remove();
            });
        },

        /** Nothing is claimed and nowhere is navigated to when it fails. */
        async 'signing out everywhere does not say Done when it did not'() {
            await withFetch(false, async (posted) => {
                // Bound directly to the button, so the panel has to be there
                // before init() runs.
                const panel = document.createElement('div');
                panel.className = 'LogoutEverywherePanel';

                const button = document.createElement('button');
                button.className = 'LogoutEverywhereButton';
                panel.appendChild(button);
                document.body.appendChild(panel);

                LogoutEverywherePanel.init();

                button.click();
                await settle();
                await settle();
                await settle();

                TestCase.assertEquals(1, posted.length);
                TestCase.assertEquals('Failed', button.textContent);

                panel.remove();
            });
        },
    }
};
