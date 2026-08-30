import { TestCase } from './TestCase.js';
import { MessageCrypto } from '../../scripts/HTMLObjects.js';

/**
 * The safety code is the only check on the one thing encryption here cannot
 * verify by itself - that the key the server handed over is really the other
 * person's. It is worth nothing unless both sides compute the same code from
 * the same pair of keys, and a different one from any other pair.
 */
export default {
    suite: 'MessageFingerprint',
    tests: {
        async 'both sides of a conversation read the same code'() {
            const alice = await MessageCrypto.generateKeypair();
            const bob = await MessageCrypto.generateKeypair();

            const alice_sees = await MessageCrypto.fingerprint(bob.publicKey, alice.publicKey);
            const bob_sees = await MessageCrypto.fingerprint(alice.publicKey, bob.publicKey);

            TestCase.assertEquals(alice_sees, bob_sees);
        },

        async 'a substituted key gives a different code'() {
            const alice = await MessageCrypto.generateKeypair();
            const bob = await MessageCrypto.generateKeypair();
            const impostor = await MessageCrypto.generateKeypair();

            const honest = await MessageCrypto.fingerprint(alice.publicKey, bob.publicKey);
            const intercepted = await MessageCrypto.fingerprint(alice.publicKey, impostor.publicKey);

            TestCase.assertFalse(honest === intercepted);
        },

        async 'the same pair always reads the same'() {
            const alice = await MessageCrypto.generateKeypair();
            const bob = await MessageCrypto.generateKeypair();

            TestCase.assertEquals(
                await MessageCrypto.fingerprint(alice.publicKey, bob.publicKey),
                await MessageCrypto.fingerprint(alice.publicKey, bob.publicKey)
            );
        },

        async 'it is short enough to read aloud and long enough to mean something'() {
            const alice = await MessageCrypto.generateKeypair();
            const bob = await MessageCrypto.generateKeypair();

            const code = await MessageCrypto.fingerprint(alice.publicKey, bob.publicKey);

            // Five groups of four hex characters: 80 bits, in blocks a person
            // can keep their place in while reading them out.
            TestCase.assertTrue(/^[0-9A-F]{4}( [0-9A-F]{4}){4}$/.test(code), 'unexpected shape: ' + code);
        },
    }
};
