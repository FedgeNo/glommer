import { Api } from '/scripts/Api.js';
import { MessageCrypto } from '/scripts/MessageCrypto.js';
import { Toast } from '/scripts/Toast.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

/**
 * The Settings section for encrypted messaging. Key generation, wrapping,
 * and rewrapping all happen here in the browser; the server only ever
 * receives the public key and the passphrase-wrapped private key. Turning it
 * on rebuilds the section in place to the same DOM the server renders for an
 * enabled account - no page reload.
 */
export class EncryptedMessagesSetting {
    static MIN_PASSPHRASE_LENGTH = 12;

    /**
     * Keys created or rewrapped since the page loaded. Preferred over what the
     * section was rendered with, which only knows what existed at that moment
     * - without this, a passphrase change right after enabling (or a second
     * change in a row) would unwrap a stale blob.
     */
    static #keys = null;

    /** The keys the section was rendered with (EncryptedMessagesSetting.php). */
    static #storedKeys(form) {
        const section = form.closest('.EncryptedMessagesSetting');

        return {
            publicKey: JSON.parse(section.dataset.publicKey),
            wrappedPrivateKey: JSON.parse(section.dataset.wrappedPrivateKey),
        };
    }

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
        const account_password = form.querySelector('[name="setupAccountPassword"]').value;

        if (!EncryptedMessagesSetting.#acceptable(passphrase, form.querySelector('[name="passphraseConfirm"]').value, account_password)) return;

        const submit_button = form.querySelector('button[type="submit"]');
        Working.start(submit_button);

        try {
            const pair = await MessageCrypto.generateKeypair();
            const wrapped = await MessageCrypto.wrapPrivateKey(pair.privateKey, passphrase);

            const result = await Api.post('/api/message-keys', {
                publicKey: pair.publicKey,
                wrappedPrivateKey: wrapped,
                password: account_password,
            }, { form });
            if (result === null) return;

            EncryptedMessagesSetting.#keys = { publicKey: pair.publicKey, wrappedPrivateKey: wrapped };

            // The tab that just made the key is already unlocked with it.
            MessageCrypto.storeUnlocked(pair.privateKey);

            // The same form serves first-time setup and the reset an enabled
            // account offers; only the former changes the section's shape.
            if (form.closest('.EncryptedMessagesSetting').querySelector('.MessageKeyPassphraseForm') !== null) {
                Toast.show('New keys created.');
                form.reset();
            } else {
                EncryptedMessagesSetting.#showEnabled(form);
                Toast.show('Encrypted messages are on.');
            }
        } finally {
            Working.stop(submit_button);
        }
    }

    /** Same key, new wrapping: unwrap under the old passphrase, rewrap under the new. */
    static async #changePassphrase(form) {
        const new_passphrase = form.querySelector('[name="newPassphrase"]').value;
        const account_password = form.querySelector('[name="rewrapAccountPassword"]').value;

        if (!EncryptedMessagesSetting.#acceptable(new_passphrase, form.querySelector('[name="newPassphraseConfirm"]').value, account_password)) return;

        const keys = EncryptedMessagesSetting.#keys ?? EncryptedMessagesSetting.#storedKeys(form);
        const private_jwk = await MessageCrypto.unwrapPrivateKey(keys.wrappedPrivateKey, form.querySelector('[name="currentPassphrase"]').value);

        if (private_jwk === null) {
            Toast.show('That isn\'t your current passphrase.');
            return;
        }

        const submit_button = form.querySelector('button[type="submit"]');
        Working.start(submit_button);

        try {
            const wrapped = await MessageCrypto.wrapPrivateKey(private_jwk, new_passphrase);

            const result = await Api.post('/api/message-keys', {
                publicKey: keys.publicKey,
                wrappedPrivateKey: wrapped,
                password: account_password,
            }, { form });
            if (result === null) return;

            EncryptedMessagesSetting.#keys = { publicKey: keys.publicKey, wrappedPrivateKey: wrapped };

            Toast.show('Passphrase changed.');
            form.reset();
        } finally {
            Working.stop(submit_button);
        }
    }

    /**
     * Swaps the section from its setup state to the enabled one the server
     * renders: a status line, the change-passphrase form, and the reset form
     * (see EncryptedMessagesSetting.php).
     */
    static #showEnabled(setup_form) {
        const section = setup_form.closest('.EncryptedMessagesSetting');
        setup_form.remove();

        const status = document.createElement('p');
        status.textContent = 'Encrypted messages are on.';
        section.appendWithSpace(status);

        const passphrase_form = document.createElement('form');
        passphrase_form.className = 'Form MessageKeyPassphraseForm';
        passphrase_form.appendWithSpace(input_field('currentPassphrase', 'Current passphrase', 'current-password'));
        passphrase_form.appendWithSpace(input_field('newPassphrase', 'New passphrase', 'new-password'));
        passphrase_form.appendWithSpace(input_field('newPassphraseConfirm', 'Confirm new passphrase', 'new-password'));
        passphrase_form.appendWithSpace(input_field('rewrapAccountPassword', 'Account password', 'current-password'));
        passphrase_form.appendWithSpace(submit_button('Change passphrase'));
        section.appendWithSpace(passphrase_form);

        const reset_form = document.createElement('form');
        reset_form.className = 'Form MessageKeySetupForm';

        const warning = document.createElement('p');
        warning.textContent = 'Forgotten your passphrase? Resetting creates new keys under a new one - but messages encrypted with the old keys can never be read again, by anyone.';
        reset_form.appendWithSpace(warning);

        reset_form.appendWithSpace(input_field('passphrase', 'New passphrase', 'new-password'));
        reset_form.appendWithSpace(input_field('passphraseConfirm', 'Confirm passphrase', 'new-password'));
        reset_form.appendWithSpace(input_field('setupAccountPassword', 'Account password', 'current-password'));
        reset_form.appendWithSpace(submit_button('Reset encryption keys'));
        section.appendWithSpace(reset_form);
    }

    /**
     * What is wrong with a proposed passphrase, or null if nothing is.
     *
     * This one secret guards every encrypted message the account will ever
     * hold, on every device, for as long as the account exists - there is no
     * second factor behind it and no reset that keeps the messages. So the bar
     * is higher than a password's, where a lockout and a reset stand behind a
     * weak choice.
     *
     * Reusing the account password is refused outright and is the important
     * one: the account password is sent to the server to authorise this very
     * change, so making it the passphrase too hands the server the key it is
     * not supposed to have, and the encryption stops meaning anything.
     */
    static passphraseProblem(passphrase, confirmation, account_password) {
        if (passphrase.length < EncryptedMessagesSetting.MIN_PASSPHRASE_LENGTH) {
            return 'Use a passphrase of at least ' + EncryptedMessagesSetting.MIN_PASSPHRASE_LENGTH + ' characters.';
        }

        if (passphrase !== confirmation) {
            return 'The passphrases don\'t match.';
        }

        if (account_password !== '' && passphrase === account_password) {
            return 'Your passphrase can\'t be your account password - that one is sent to the server, and this one must never be.';
        }

        if (new Set(passphrase).size < 5) {
            return 'That passphrase repeats too few different characters to be worth much. Use a longer phrase.';
        }

        return null;
    }

    static #acceptable(passphrase, confirmation, account_password) {
        const problem = EncryptedMessagesSetting.passphraseProblem(passphrase, confirmation, account_password);

        if (problem !== null) {
            Toast.show(problem);

            return false;
        }

        return true;
    }
}

function input_field(name, label, autocomplete) {
    const field = document.createElement('div');
    field.className = 'InputField';

    const label_element = document.createElement('label');
    label_element.htmlFor = name;
    label_element.textContent = label;
    field.appendWithSpace(label_element);

    const input = document.createElement('input');
    input.type = 'password';
    input.name = name;
    input.id = name;
    input.placeholder = label;
    input.setAttribute('autocomplete', autocomplete);
    field.appendWithSpace(input);

    return field;
}

function submit_button(label) {
    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'Button SubmitButton';
    button.textContent = label;

    return button;
}

ReadyHandler.add(EncryptedMessagesSetting.init);
