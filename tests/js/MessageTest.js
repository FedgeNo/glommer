import { TestCase } from './TestCase.js';

document.cookie = 'APP-CONFIG=' + encodeURIComponent(JSON.stringify({
    currentUserId: 1,
}));

const { Message } = await import('../../scripts/Message.js');

export default {
    suite: 'Message',
    tests: {
        'an encrypted payload renders the locked placeholder and carries its envelope'() {
            const message = Message.fromData({
                messageId: 9,
                senderId: 2,
                recipientId: 1,
                body: null,
                bodyCiphertext: '{"v":1}',
                createdAt: '2026-08-01 12:00:00',
            });

            const element = message.toElement();

            TestCase.assertTrue(element.classList.contains('Encrypted'));
            TestCase.assertTrue(element.classList.contains('Locked'));
            TestCase.assertEquals('{"v":1}', element.dataset.cipherEnvelope);
            TestCase.assertEquals('9', element.dataset.messageId);
            TestCase.assertEquals('Encrypted message', element.querySelector('.MessageLine p').textContent);
        },

        'a plaintext payload renders its body with no envelope'() {
            const message = Message.fromData({
                messageId: 10,
                senderId: 2,
                recipientId: 1,
                body: 'hello there',
                bodyCiphertext: null,
                createdAt: '2026-08-01 12:00:00',
            });

            const element = message.toElement();

            TestCase.assertEquals('hello there', element.querySelector('.MessageLine p').textContent);
            TestCase.assertFalse(element.classList.contains('Locked'));
            TestCase.assertFalse('cipherEnvelope' in element.dataset);
        },
    }
};
