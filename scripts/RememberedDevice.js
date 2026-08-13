import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

/**
 * Signing one remembered device out - a browser somebody once ticked "remember
 * me" in, which can log in as them without a password until it is revoked.
 *
 * The only place this is done. User.js carried a second copy for a while,
 * reached from a page the device list is not on, so the one that ran was the
 * one with no confirmation and no check that the request landed.
 */
export class RememberedDevice {
    static init() {
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('.RememberedDeviceRevokeButton');

            if (!button || !button.dataset.tokenId) {
                return;
            }

            event.preventDefault();

            // Asked first: revoking is not undoable, and the row says only
            // where and when the device last appeared, so it is quite possible
            // to be about to sign out the phone in your own pocket.
            if (!await Dialog.confirm('Revoke this device? It will be signed out and have to log in again.')) {
                return;
            }

            button.textContent = 'Revoking…';
            Working.start(button);

            // Api.post answers null rather than throwing. Taking the card away
            // regardless said the device was signed out whether or not it was,
            // about the one thing on this page somebody is looking at because
            // they think a stranger is holding it.
            const revoked = await Api.post('/api/revoke-session', { tokenId: button.dataset.tokenId });

            if (!revoked) {
                button.textContent = 'Failed';
                Working.stop(button);

                return;
            }

            DOMUtils.slideOut(button.closest('.RememberedDevice'));
        });
    }
}

ReadyHandler.add(RememberedDevice.init);
