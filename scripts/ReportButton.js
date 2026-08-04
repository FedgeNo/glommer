import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Every Report button on the site, delegated in one place - report buttons
 * appear on posts, profiles, and message threads, and the last of those has
 * no Post on the page to have loaded a handler.
 *
 * Reporting an encrypted message carries that one message's revealed key
 * (see MessageCrypto.js) so the server can verify and open exactly what was
 * reported - one message, never the conversation.
 */
export class ReportButton {
    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.ReportButton');
            if (!button) return;
            ReportButton.#report(button);
        });
    }

    static async #report(button) {
        const reason = await Dialog.prompt('Why are you reporting this?', { confirmLabel: 'Report' });
        if (reason === null) return;

        const payload = {
            targetType: button.dataset.targetType,
            targetId: button.dataset.targetId,
            reason,
        };

        if (button.dataset.targetType === 'message' && button.closest('.Message')?.dataset.cipherEnvelope) {
            const { MessageCrypto } = await import('/scripts/MessageCrypto.js');

            // Still locked with the thread key in hand means this envelope
            // didn't open - it was encrypted under keys that have since been
            // reset, so there is no key left to reveal and nothing the server
            // could verify.
            if (button.closest('.Message').classList.contains('Locked')) {
                Toast.show(MessageCrypto.threadKey() !== null
                    ? 'This message was encrypted with keys that no longer exist, so it can\'t be verified or reported.'
                    : 'Unlock the conversation before reporting an encrypted message.');
                return;
            }

            payload.revealedKey = await MessageCrypto.revealKeyForMessage(button.dataset.targetId);
        }

        button.disabled = true;

        try {
            const result = await Api.post('/api/report', payload);
            if (!result) return;
            button.textContent = 'Reported';
        } finally {
            button.disabled = false;
        }
    }
}

ReadyHandler.add(ReportButton.init);
