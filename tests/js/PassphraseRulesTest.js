import { TestCase } from './TestCase.js';
import { EncryptedMessagesSetting } from '../../scripts/EncryptedMessagesSetting.js';

/**
 * The passphrase is the only secret guarding every encrypted message an
 * account will ever hold, on every device, with no second factor behind it and
 * no reset that keeps the messages. These are the rules that stand between a
 * member and a choice that would undo the encryption entirely.
 */
export default {
    suite: 'PassphraseRules',
    tests: {
        'a decent passphrase is accepted'() {
            TestCase.assertNull(EncryptedMessagesSetting.passphraseProblem('correct horse battery', 'correct horse battery', 'hunter2'));
        },

        'a short one is refused'() {
            TestCase.assertNotNull(EncryptedMessagesSetting.passphraseProblem('short', 'short', 'hunter2'));
        },

        'a mistyped confirmation is refused'() {
            TestCase.assertNotNull(EncryptedMessagesSetting.passphraseProblem('correct horse battery', 'correct hoarse battery', 'hunter2'));
        },

        /**
         * The important one. The account password is sent to the server to
         * authorise the change, so reusing it as the passphrase hands the
         * server the key the whole design exists to keep from it.
         */
        'the account password cannot be reused as the passphrase'() {
            const password = 'a perfectly fine password';

            TestCase.assertNotNull(EncryptedMessagesSetting.passphraseProblem(password, password, password));
        },

        'a passphrase of one repeated character is refused however long'() {
            TestCase.assertNotNull(EncryptedMessagesSetting.passphraseProblem('aaaaaaaaaaaaaaaa', 'aaaaaaaaaaaaaaaa', 'hunter2'));
        },

        /**
         * The change-passphrase form asks for the account password too, but a
         * caller with nothing to compare against must still get the other
         * checks rather than an exception.
         */
        'the check still works with no account password to compare'() {
            TestCase.assertNull(EncryptedMessagesSetting.passphraseProblem('correct horse battery', 'correct horse battery', ''));
        },
    }
};
