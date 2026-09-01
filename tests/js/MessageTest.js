import { TestCase, write_client_config } from './TestCase.js';

write_client_config({ currentUserId: 1 });

const { Message } = await import('../../scripts/HTMLObjects.js');

function withLoadingPage(readyState, run) {
    const realScrollTo = window.scrollTo;
    const scrolls = [];

    Object.defineProperty(document, 'readyState', { value: readyState, configurable: true });
    Object.defineProperty(document.body, 'scrollHeight', { value: 1234, configurable: true });
    window.scrollTo = options => scrolls.push(options);

    const composer = document.createElement('form');
    composer.className = 'MessageComposer';
    const textarea = document.createElement('textarea');
    composer.appendChild(textarea);
    document.body.appendChild(composer);

    try {
        run({ scrolls, textarea });
    } finally {
        composer.remove();
        window.scrollTo = realScrollTo;
        delete document.readyState;
        delete document.body.scrollHeight;
    }
}

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
            TestCase.assertEquals('Encrypted message', element.querySelector('.MessageBody').textContent);
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

            TestCase.assertEquals('hello there', element.querySelector('.MessageBody').textContent);
            TestCase.assertFalse(element.classList.contains('Locked'));
            TestCase.assertFalse('cipherEnvelope' in element.dataset);
        },

        /** The same element the server renders, so a live message keeps its shape too. */
        'a message arriving live keeps the lines it was written with'() {
            const written = "def greet(name):\n    print('hi ' + name)";

            const message = Message.fromData({
                messageId: 11,
                senderId: 2,
                recipientId: 1,
                body: written,
                bodyCiphertext: null,
                createdAt: '2026-08-01 12:00:00',
            });

            const body = message.toElement().querySelector('.MessageBody');

            TestCase.assertEquals('PRE', body.tagName);
            TestCase.assertEquals(written, body.textContent);
        },

        'a thread waits for page load before scrolling to the bottom'() {
            withLoadingPage('loading', ({ scrolls, textarea }) => {
                Message.init();

                TestCase.assertEquals(0, scrolls.length);

                window.dispatchEvent(new window.Event('load'));

                TestCase.assertEquals(1, scrolls.length);
                TestCase.assertEquals(1234, scrolls[0].top);
                TestCase.assertTrue(document.activeElement === textarea);
            });
        },

        'a thread still scrolls when its module arrives after page load'() {
            withLoadingPage('complete', ({ scrolls, textarea }) => {
                Message.init();

                TestCase.assertEquals(1, scrolls.length);
                TestCase.assertEquals(1234, scrolls[0].top);
                TestCase.assertTrue(document.activeElement === textarea);
            });
        },
    }
};
