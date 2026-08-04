import { Api } from '/scripts/Api.js';
import { MessageCrypto } from '/scripts/MessageCrypto.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * The Settings section for encrypted messaging. Key generation, wrapping,
 * and rewrapping all happen here in the browser; the server only ever
 * receives the public key and the passphrase-wrapped private key.
 */
export class EncryptedMessagesSetting {
    static MIN_PASSPHRASE_LENGTH = 8;

    static init() {
        document.addEventListener('submit', (event) => {
            const setup_form = event.target.closest('.MessageKeySetupForm');
            if (setup_form) {
                event.preventDefault();
                EncryptedMessagesSetting.#setup(setup_form);
                return;
            }

            const passphrase_form = event.target.closest('.MessageKeyPassphraseForm');
            if (passphrase_form) {
                event.preventDefault();
                EncryptedMessagesSetting.#changePassphrase(passphrase_form);
            }
        });
    }

    /** Creates a keypair (or replaces one - the reset variant) and stores it wrapped. */
    static async #setup(form) {
        const passphrase = form.querySelector('[name="passphrase"]').value;

        if (!EncryptedMessagesSetting.#acceptable(passphrase, form.querySelector('[name="passphraseConfirm"]').value)) return;

        const submit_button = form.querySelector('button[type="submit"]');
        submit_button.disabled = true;

        try {
            const pair = await MessageCrypto.generateKeypair();
            const wrapped = await MessageCrypto.wrapPrivateKey(pair.privateKey, passphrase);

            const result = await Api.post('/api/message-keys', {
                publicKey: pair.publicKey,
                wrappedPrivateKey: wrapped,
            });
            if (result === null) return;

            // The tab that just made the key is already unlocked with it.
            MessageCrypto.storeUnlocked(pair.privateKey);
            window.location.reload();
        } finally {
            submit_button.disabled = false;
        }
    }

    /** Same key, new wrapping: unwrap under the old passphrase, rewrap under the new. */
    static async #changePassphrase(form) {
        const new_passphrase = form.querySelector('[name="newPassphrase"]').value;

        if (!EncryptedMessagesSetting.#acceptable(new_passphrase, form.querySelector('[name="newPassphraseConfirm"]').value)) return;

        const keys = ClientConfig.get('messageKeys');
        const private_jwk = await MessageCrypto.unwrapPrivateKey(keys.wrappedPrivateKey, form.querySelector('[name="currentPassphrase"]').value);

        if (private_jwk === null) {
            Toast.show('That isn\'t your current passphrase.');
            return;
        }

        const submit_button = form.querySelector('button[type="submit"]');
        submit_button.disabled = true;

        try {
            const result = await Api.post('/api/message-keys', {
                publicKey: keys.publicKey,
                wrappedPrivateKey: await MessageCrypto.wrapPrivateKey(private_jwk, new_passphrase),
            });
            if (result === null) return;

            Toast.show('Passphrase changed.');
            form.reset();
        } finally {
            submit_button.disabled = false;
        }
    }

    static #acceptable(passphrase, confirmation) {
        if (passphrase.length < EncryptedMessagesSetting.MIN_PASSPHRASE_LENGTH) {
            Toast.show('Use a passphrase of at least ' + EncryptedMessagesSetting.MIN_PASSPHRASE_LENGTH + ' characters.');
            return false;
        }

        if (passphrase !== confirmation) {
            Toast.show('The passphrases don\'t match.');
            return false;
        }

        return true;
    }
}

ReadyHandler.add(EncryptedMessagesSetting.init);
