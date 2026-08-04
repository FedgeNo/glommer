import { MessageCrypto } from '/scripts/MessageCrypto.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * The safety code for an encrypted conversation, and the memory of having
 * checked it.
 *
 * Computed here from the keys this browser is encrypting with, never taken
 * from the server: the server is what introduces the two sides to each other's
 * keys, so a code it supplied would agree with whatever it had handed over. It
 * cannot make both sides' codes match without holding the two real keys, which
 * is what makes reading them to each other worth doing.
 *
 * A confirmed code is remembered in this browser, for the same reason - a note
 * kept on the server could be rewritten by the server it is meant to catch. So
 * verification is per browser, and a code that changes afterwards is called out
 * rather than quietly accepted.
 */
export class MessageKeyFingerprint {
    static init() {
        const block = document.querySelector('.MessageKeyFingerprint');
        const form = document.querySelector('.MessageUnlockForm');
        const thread = document.querySelector('.MessageList[data-other-user-id]');

        if (block === null || form === null || thread === null) return;

        MessageKeyFingerprint.#show(block, form, thread.dataset.otherUserId);

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.MessageKeyVerifyButton')) return;

            const code = block.querySelector('.MessageKeyFingerprintCode').textContent;

            if (code === '') return;

            localStorage.setItem(MessageKeyFingerprint.#storageKey(thread.dataset.otherUserId), code);
            MessageKeyFingerprint.#markVerified(block);
            Toast.show('Marked as verified.');
        });
    }

    static async #show(block, form, other_user_id) {
        const code = await MessageCrypto.fingerprint(
            JSON.parse(form.dataset.otherPublicKey),
            JSON.parse(form.dataset.ownPublicKey)
        );

        block.querySelector('.MessageKeyFingerprintCode').textContent = code;

        const confirmed = localStorage.getItem(MessageKeyFingerprint.#storageKey(other_user_id));

        if (confirmed === null) return;

        if (confirmed === code) {
            MessageKeyFingerprint.#markVerified(block);

            return;
        }

        // Either one of them reset their keys, or somebody replaced one. This
        // cannot tell which, and says so rather than guessing.
        block.classList.add('Changed');

        const warning = document.createElement('p');
        warning.className = 'MessageKeyFingerprintWarning';
        warning.textContent = 'This code has changed since you checked it. That happens when one of you resets your encryption keys - but it is also what someone reading this conversation would look like. Check the new code with them before trusting it.';
        block.prepend(warning);
    }

    static #markVerified(block) {
        block.classList.add('Verified');
        block.classList.remove('Changed');

        const button = block.querySelector('.MessageKeyVerifyButton');

        if (button !== null) {
            button.remove();
        }

        const done = document.createElement('p');
        done.className = 'MessageKeyFingerprintVerified';
        done.textContent = 'You have checked this code.';
        block.appendWithSpace(done);
    }

    static #storageKey(other_user_id) {
        return 'messageKeyVerified:' + other_user_id;
    }
}

ReadyHandler.add(MessageKeyFingerprint.init);
