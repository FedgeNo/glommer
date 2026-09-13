import { TestCase, write_client_config } from './TestCase.js';
import { VideoCall } from '../../scripts/Controllers.js';
import { Api } from '../../scripts/Runtime.js';

export default {
    suite: 'VideoCall',
    tests: {
        async 'presence waits for completion and pagehide prevents a late restart'() {
            write_client_config({ currentUserId: 30 });
            const composer = document.createElement('form');
            composer.className = 'MessageComposer';
            composer.dataset.otherUserId = '20';
            document.body.append(composer);
            const originals = { post: Api.post, timeout: globalThis.setTimeout,
                clear: globalThis.clearTimeout, interval: globalThis.setInterval };
            const timers = new Map();
            let next = 0, requests = 0, finish;
            globalThis.setTimeout = (callback, delay) => { timers.set(++next, { callback, delay }); return next; };
            globalThis.clearTimeout = id => timers.delete(id);
            globalThis.setInterval = () => { throw new Error('presence must not use setInterval'); };
            Api.post = (path, body) => {
                if (body.leaving) return Promise.resolve({});
                requests++;
                return new Promise(resolve => { finish = resolve; });
            };
            const settle = () => new Promise(resolve => originals.timeout(resolve, 0));
            try {
                VideoCall.init();
                TestCase.assertEquals(1, requests);
                TestCase.assertEquals(0, timers.size, 'no next heartbeat while the first is pending');
                window.dispatchEvent(new window.Event('pageshow'));
                TestCase.assertEquals(1, requests, 'starting an active loop cannot overlap it');
                finish(null);
                await settle();
                TestCase.assertEquals(1, timers.size, 'even a failed request schedules one retry');
                const [id, timer] = [...timers][0];
                TestCase.assertEquals(10000, timer.delay);
                timers.delete(id);
                timer.callback();
                TestCase.assertEquals(2, requests);
                TestCase.assertEquals(0, timers.size);
                window.dispatchEvent(new window.Event('pagehide'));
                finish({ otherUserPresent: false });
                await settle();
                TestCase.assertEquals(0, timers.size, 'late completion must not restart a stopped loop');
            } finally {
                window.dispatchEvent(new window.Event('pagehide'));
                finish?.(null);
                await settle();
                Api.post = originals.post;
                globalThis.setTimeout = originals.timeout;
                globalThis.clearTimeout = originals.clear;
                globalThis.setInterval = originals.interval;
                composer.remove();
            }
        },
        // Every page loads main.js, and the controller is only initialized on a thread -
        // but init() still has to be safe when there is no thread to attach to,
        // since that is also what a signed-out reader gets.
        'init() does nothing when there is no message thread'() {
            document.querySelectorAll('.MessageList').forEach((list) => list.remove());

            VideoCall.init();

            TestCase.assertTrue(true, 'init() should return quietly rather than throw');
        },
        'init() does nothing for a thread with no other user named'() {
            const list = document.createElement('ul');
            list.className = 'MessageList';
            document.body.appendChild(list);

            VideoCall.init();
            list.remove();

            TestCase.assertTrue(true, 'a list without data-other-user-id is not a thread it can act on');
        },
    }
};
