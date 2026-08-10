import { TestCase } from './TestCase.js';
import { MessageComposer } from '../../scripts/MessageComposer.js';

/**
 * The composer is fixed to the bottom of the window, so nothing in the flow
 * knows it is there and the page has to leave the room by hand. A fixed figure
 * only holds while the composer is one fixed size, and it is not: the video
 * call button appears when the other person turns up and adds a row, and the
 * last messages in the thread end up underneath it.
 */

/** jsdom has no ResizeObserver, so one is lent for the length of a test. */
function withResizeObserver(run) {
    const observed = [];
    const original = globalThis.ResizeObserver;

    globalThis.ResizeObserver = class {
        constructor(callback) { this.callback = callback; }
        observe(element) { observed.push({ element, fire: () => this.callback() }); }
        disconnect() {}
    };

    try {
        return run(observed);
    } finally {
        if (original === undefined) {
            delete globalThis.ResizeObserver;
        } else {
            globalThis.ResizeObserver = original;
        }
    }
}

function mounted(height) {
    document.body.className = 'PageBody MessagesPage';
    document.body.style.removeProperty('--composer-height');

    const form = document.createElement('form');
    form.className = 'Card d-flex flex-column MessageComposer';
    // jsdom lays nothing out, so the height it would have measured is stated.
    Object.defineProperty(form, 'offsetHeight', { value: height, configurable: true });
    document.body.appendChild(form);

    return form;
}

const reserved = () => document.body.style.getPropertyValue('--composer-height');

export default {
    suite: 'MessageComposer',
    tests: {
        'the page is told how tall the composer is'() {
            const form = mounted(112);

            MessageComposer.init();

            TestCase.assertEquals('112px', reserved());
            document.body.removeChild(form);
        },
        // What the video call button arriving does: another row, and the last
        // messages disappearing behind it.
        'and told again when it grows'() {
            const form = mounted(112);

            withResizeObserver((observed) => {
                MessageComposer.init();

                TestCase.assertEquals(1, observed.length, 'the composer is what gets watched');
                TestCase.assertTrue(observed[0].element === form);

                Object.defineProperty(form, 'offsetHeight', { value: 156, configurable: true });
                observed[0].fire();

                TestCase.assertEquals('156px', reserved());
            });

            document.body.removeChild(form);
        },
        // Every browser has ResizeObserver, but the room still has to be right
        // if measuring changes is ever unavailable - the composer starting
        // taller than the fallback is the whole failure being fixed.
        'the room is reserved even with nothing to watch changes'() {
            const form = mounted(200);

            MessageComposer.init();

            TestCase.assertEquals('200px', reserved());
            document.body.removeChild(form);
        },
    }
};
