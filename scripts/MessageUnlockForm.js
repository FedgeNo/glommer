import { MessageCrypto } from '/scripts/MessageCrypto.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { Message } from '/scripts/Message.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Opens an encrypted conversation: unwraps the viewer's private key with
 * their passphrase (all in the browser - see MessageCrypto.js), derives the
 * conversation key, and decrypts every envelope on the page. Once the tab
 * holds the unlocked key, the form hides itself and later page loads unlock
 * silently.
 */
export class MessageUnlockForm {
    static init() {
        const stored = MessageCrypto.loadUnlocked();

        if (stored !== null) {
            MessageUnlockForm.#activate(stored);
        }

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('.MessageUnlockForm');
            if (!form) return;
            event.preventDefault();

            const passphrase_input = form.querySelector('[name="messagePassphrase"]');
            const wrapped = ClientConfig.get('messageEncryption').wrappedPrivateKey;
            const private_jwk = await MessageCrypto.unwrapPrivateKey(wrapped, passphrase_input.value);

            if (private_jwk === null) {
                Toast.show('That passphrase doesn\'t unlock your messages.');
                passphrase_input.focus();
                return;
            }

            MessageCrypto.storeUnlocked(private_jwk);
            passphrase_input.value = '';
            await MessageUnlockForm.#activate(private_jwk);
        });
    }

    static async #activate(private_jwk) {
        const encryption = ClientConfig.get('messageEncryption');
        if (!encryption) return;

        try {
            MessageCrypto.setThreadKey(await MessageCrypto.conversationKey(private_jwk, encryption.otherPublicKey));
        } catch {
            // A stored key that doesn't parse as a P-256 key (say, after a
            // reset in another tab) just leaves the thread locked - the form
            // stays up and a fresh passphrase replaces it.
            MessageCrypto.clearUnlocked();
            return;
        }

        document.querySelectorAll('.MessageUnlockForm').forEach((form) => { form.hidden = true; });

        document.querySelectorAll('.Message[data-cipher-envelope]').forEach((article) => {
            Message.decryptInto(article);
        });
    }
}

ReadyHandler.add(MessageUnlockForm.init);
