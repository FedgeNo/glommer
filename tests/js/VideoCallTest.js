import { TestCase } from './TestCase.js';
import { VideoCall } from '../../scripts/Controllers.js';

export default {
    suite: 'VideoCall',
    tests: {
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
