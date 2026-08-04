/**
 * The cryptography behind end-to-end encrypted messages, all WebCrypto.
 *
 * Each member holds a P-256 ECDH keypair (generated here, in the browser).
 * The private key is wrapped under a passphrase-derived key (PBKDF2) and the
 * wrapped blob is stored server-side, which is what lets the same passphrase
 * unlock the history from any browser - the server only ever sees ciphertext.
 *
 * A conversation's key is derived by ECDH against the other person's public
 * key, so both sides compute the same one. Each message is encrypted under
 * its own random key, which travels wrapped under the conversation key inside
 * the envelope (see MessageEnvelope.php) - the shape that makes reporting
 * work: revealing one message's key opens that message and nothing else.
 */

const CURVE = { name: 'ECDH', namedCurve: 'P-256' };
const PBKDF2_ITERATIONS = 600000;

function to_base64(bytes) {
    return btoa(String.fromCharCode(...new Uint8Array(bytes)));
}

function from_base64(text) {
    return Uint8Array.from(atob(text), (character) => character.charCodeAt(0));
}

export class MessageCrypto {
    /** The open conversation's derived AES key, set by MessageUnlockForm.js. */
    static #threadKey = null;

    /** messageId -> envelope JSON, so a report can reveal that message's key. */
    static #envelopes = new Map();

    static async generateKeypair() {
        const pair = await crypto.subtle.generateKey(CURVE, true, ['deriveBits']);

        return {
            publicKey: await crypto.subtle.exportKey('jwk', pair.publicKey),
            privateKey: await crypto.subtle.exportKey('jwk', pair.privateKey),
        };
    }

    static async wrapPrivateKey(private_jwk, passphrase) {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const key = await MessageCrypto.#passphraseKey(passphrase, salt, PBKDF2_ITERATIONS, 'encrypt');

        const ciphertext = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv },
            key,
            new TextEncoder().encode(JSON.stringify(private_jwk))
        );

        return {
            salt: to_base64(salt),
            iterations: PBKDF2_ITERATIONS,
            iv: to_base64(iv),
            ciphertext: to_base64(ciphertext),
        };
    }

    /**
     * Null on a wrong passphrase - GCM authenticates as it decrypts, so a bad
     * unwrap is detected rather than yielding garbage.
     */
    static async unwrapPrivateKey(wrapped, passphrase) {
        try {
            const key = await MessageCrypto.#passphraseKey(passphrase, from_base64(wrapped.salt), wrapped.iterations, 'decrypt');

            const plaintext = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: from_base64(wrapped.iv) },
                key,
                from_base64(wrapped.ciphertext)
            );

            return JSON.parse(new TextDecoder().decode(plaintext));
        } catch {
            return null;
        }
    }

    static async conversationKey(private_jwk, other_public_jwk) {
        const private_key = await crypto.subtle.importKey('jwk', private_jwk, CURVE, false, ['deriveBits']);
        const public_key = await crypto.subtle.importKey('jwk', other_public_jwk, CURVE, false, []);

        const shared_secret = await crypto.subtle.deriveBits({ name: 'ECDH', public: public_key }, private_key, 256);
        const hkdf_key = await crypto.subtle.importKey('raw', shared_secret, 'HKDF', false, ['deriveKey']);

        return crypto.subtle.deriveKey(
            { name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(0), info: new TextEncoder().encode('glommer-message-v1') },
            hkdf_key,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt', 'decrypt']
        );
    }

    /**
     * A short code standing for the pair of public keys this conversation is
     * encrypted with. Both sides compute the same one - the keys are sorted
     * before hashing, so it does not matter who is reading - and two people
     * who read it to each other over any other channel can tell whether they
     * are really talking to each other.
     *
     * It is the answer to the one thing encryption here cannot check by
     * itself: the server is what tells each browser the other's key, so a
     * server that handed out a key of its own would sit in the middle
     * undetected. It cannot make both codes agree, because it does not get to
     * choose either real key - so the codes differ, and that is the tell.
     */
    static async fingerprint(one_jwk, other_jwk) {
        const material = [one_jwk, other_jwk]
            .map((jwk) => jwk.x + '.' + jwk.y)
            .sort()
            .join('|');

        const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(material));
        const hex = Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');

        return (hex.slice(0, 20).toUpperCase().match(/.{4}/g) ?? []).join(' ');
    }

    /** Builds the envelope JSON api/send-message.php takes. */
    static async encrypt(conversation_key, text) {
        const message_key_bytes = crypto.getRandomValues(new Uint8Array(32));
        const message_key = await crypto.subtle.importKey('raw', message_key_bytes, 'AES-GCM', false, ['encrypt']);

        const iv = crypto.getRandomValues(new Uint8Array(12));
        const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, message_key, new TextEncoder().encode(text));

        const key_iv = crypto.getRandomValues(new Uint8Array(12));
        const wrapped_key = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: key_iv }, conversation_key, message_key_bytes);

        return JSON.stringify({
            v: 1,
            iv: to_base64(iv),
            ct: to_base64(ciphertext),
            kiv: to_base64(key_iv),
            wk: to_base64(wrapped_key),
        });
    }

    /** Null when the envelope doesn't open under this conversation key. */
    static async decrypt(conversation_key, envelope) {
        try {
            const fields = JSON.parse(envelope);
            const message_key = await crypto.subtle.importKey('raw', await MessageCrypto.#messageKeyBytes(conversation_key, fields), 'AES-GCM', false, ['decrypt']);

            const plaintext = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: from_base64(fields.iv) },
                message_key,
                from_base64(fields.ct)
            );

            return new TextDecoder().decode(plaintext);
        } catch {
            return null;
        }
    }

    // --- The open thread's state, shared between the unlock form, the
    // composer, live messages, and the report flow ---

    static setThreadKey(key) {
        MessageCrypto.#threadKey = key;
    }

    static threadKey() {
        return MessageCrypto.#threadKey;
    }

    static rememberEnvelope(message_id, envelope) {
        MessageCrypto.#envelopes.set(Number(message_id), envelope);
    }

    /**
     * The revealed per-message key a report of an encrypted message carries
     * (base64), or null when this message isn't a decryptable encrypted one.
     */
    static async revealKeyForMessage(message_id) {
        const envelope = MessageCrypto.#envelopes.get(Number(message_id));

        if (envelope === undefined || MessageCrypto.#threadKey === null) {
            return null;
        }

        try {
            return to_base64(await MessageCrypto.#messageKeyBytes(MessageCrypto.#threadKey, JSON.parse(envelope)));
        } catch {
            return null;
        }
    }

    // --- The tab's unlocked private key. sessionStorage on purpose: it is
    // what makes every page load in the tab not re-ask the passphrase, it
    // dies with the tab, and a script that could read it could equally read
    // the decrypted messages out of the DOM - it guards against the server
    // and the database, not against the page itself. ---

    static storeUnlocked(private_jwk) {
        sessionStorage.setItem('messagePrivateKey', JSON.stringify(private_jwk));
    }

    static loadUnlocked() {
        const stored = sessionStorage.getItem('messagePrivateKey');

        return stored === null ? null : JSON.parse(stored);
    }

    static clearUnlocked() {
        sessionStorage.removeItem('messagePrivateKey');
    }

    static async #passphraseKey(passphrase, salt, iterations, usage) {
        const passphrase_key = await crypto.subtle.importKey('raw', new TextEncoder().encode(passphrase), 'PBKDF2', false, ['deriveKey']);

        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
            passphrase_key,
            { name: 'AES-GCM', length: 256 },
            false,
            [usage]
        );
    }

    static async #messageKeyBytes(conversation_key, fields) {
        return crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: from_base64(fields.kiv) },
            conversation_key,
            from_base64(fields.wk)
        );
    }
}
