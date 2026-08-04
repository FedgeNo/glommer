import { TestCase } from './TestCase.js';
import { MessageCrypto } from '../../scripts/MessageCrypto.js';

function from_base64(text) {
    return Uint8Array.from(atob(text), (character) => character.charCodeAt(0));
}

export default {
    suite: 'MessageCrypto',
    tests: {
        async 'a wrapped private key unwraps with the right passphrase and refuses the wrong one'() {
            const pair = await MessageCrypto.generateKeypair();
            const wrapped = await MessageCrypto.wrapPrivateKey(pair.privateKey, 'correct horse');

            const unwrapped = await MessageCrypto.unwrapPrivateKey(wrapped, 'correct horse');
            TestCase.assertNotNull(unwrapped);
            TestCase.assertEquals(pair.privateKey.d, unwrapped.d);

            TestCase.assertNull(await MessageCrypto.unwrapPrivateKey(wrapped, 'wrong horse'));
        },

        async 'both sides of a conversation derive the same key'() {
            const alice = await MessageCrypto.generateKeypair();
            const bob = await MessageCrypto.generateKeypair();

            const alice_key = await MessageCrypto.conversationKey(alice.privateKey, bob.publicKey);
            const bob_key = await MessageCrypto.conversationKey(bob.privateKey, alice.publicKey);

            const envelope = await MessageCrypto.encrypt(alice_key, 'hello from alice');
            TestCase.assertEquals('hello from alice', await MessageCrypto.decrypt(bob_key, envelope));
        },

        async 'a third party cannot decrypt'() {
            const alice = await MessageCrypto.generateKeypair();
            const bob = await MessageCrypto.generateKeypair();
            const eve = await MessageCrypto.generateKeypair();

            const alice_key = await MessageCrypto.conversationKey(alice.privateKey, bob.publicKey);
            const eve_key = await MessageCrypto.conversationKey(eve.privateKey, alice.publicKey);

            const envelope = await MessageCrypto.encrypt(alice_key, 'not for eve');
            TestCase.assertNull(await MessageCrypto.decrypt(eve_key, envelope));
        },

        async 'envelopes carry the fields the server validates'() {
            const alice = await MessageCrypto.generateKeypair();
            const bob = await MessageCrypto.generateKeypair();
            const key = await MessageCrypto.conversationKey(alice.privateKey, bob.publicKey);

            const fields = JSON.parse(await MessageCrypto.encrypt(key, 'shape check'));

            TestCase.assertEquals(1, fields.v);
            TestCase.assertEquals(12, from_base64(fields.iv).length);
            TestCase.assertEquals(12, from_base64(fields.kiv).length);
            TestCase.assertEquals(48, from_base64(fields.wk).length);
            TestCase.assertTrue(from_base64(fields.ct).length > 16);
        },

        async 'a revealed message key opens that message the way the server does'() {
            const alice = await MessageCrypto.generateKeypair();
            const bob = await MessageCrypto.generateKeypair();
            const conversation_key = await MessageCrypto.conversationKey(bob.privateKey, alice.publicKey);

            const envelope = await MessageCrypto.encrypt(conversation_key, 'the reported message');
            MessageCrypto.rememberEnvelope(42, envelope);
            MessageCrypto.setThreadKey(conversation_key);

            const revealed = await MessageCrypto.revealKeyForMessage(42);
            TestCase.assertNotNull(revealed);

            // The server's side of the report: the revealed key alone, against
            // the stored envelope - no conversation key in sight.
            const fields = JSON.parse(envelope);
            const message_key = await crypto.subtle.importKey('raw', from_base64(revealed), 'AES-GCM', false, ['decrypt']);
            const plaintext = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: from_base64(fields.iv) }, message_key, from_base64(fields.ct));

            TestCase.assertEquals('the reported message', new TextDecoder().decode(plaintext));
        },

        async 'revealKeyForMessage is null for unknown messages'() {
            TestCase.assertNull(await MessageCrypto.revealKeyForMessage(9999));
        },
    }
};
