import { TestCase } from './TestCase.js';
import { VideoCallTestPanel } from '../../scripts/Controllers.js';

/**
 * The admin check's verdict. Each case breaks call setup at a different link in
 * the chain and asserts the page says the right thing about it - the point of
 * the verdict is that a blocked STUN port and a missing WebRTC stack are not the
 * same answer, even though both end with no call button.
 */

// init() registers one delegated listener on document, so it is done once for
// the file rather than per case.
let initialised = false;

function panel() {
    document.body.replaceChildren();

    const wrap = document.createElement('div');
    wrap.className = 'VideoCallTestPanel';

    const button = document.createElement('button');
    button.className = 'VideoCallTestButton';

    const results = document.createElement('ul');
    results.className = 'VideoCallTestResults';

    const verdict = document.createElement('p');
    verdict.className = 'VideoCallTestVerdict';

    wrap.append(button, results, verdict);
    document.body.appendChild(wrap);

    if (!initialised) {
        VideoCallTestPanel.init();
        initialised = true;
    }

    return { button, verdict };
}

function secureContext(value) {
    Object.defineProperty(window, 'isSecureContext', { value, configurable: true });
}

/** The run is fired by a click and not awaited, so the verdict is waited on. */
async function verdictAfterRun({ button, verdict }) {
    button.click();

    for (let attempt = 0; attempt < 400 && verdict.textContent === ''; attempt++) {
        await new Promise((resolve) => setTimeout(resolve, 5));
    }

    return verdict;
}

export default {
    suite: 'VideoCallTestPanel',
    tests: {
        'the verdict is empty until a run finishes'() {
            const { verdict } = panel();

            TestCase.assertEquals('', verdict.textContent, 'nothing should be declared before the check runs');
        },

        async 'an insecure page is told that is what stops it'() {
            secureContext(false);
            delete globalThis.RTCPeerConnection;

            const verdict = await verdictAfterRun(panel());

            TestCase.assertTrue(verdict.classList.contains('Failed'), 'an insecure context cannot make calls');
            TestCase.assertTrue(verdict.textContent.includes('HTTPS'), 'the verdict should name HTTPS as the cause, got: ' + verdict.textContent);
        },

        async 'a browser without WebRTC is told it is the browser'() {
            secureContext(true);
            delete globalThis.RTCPeerConnection;

            const verdict = await verdictAfterRun(panel());

            TestCase.assertTrue(verdict.classList.contains('Failed'), 'no WebRTC means no calls');
            TestCase.assertTrue(verdict.textContent.includes('no WebRTC support'), 'the verdict should blame the browser, got: ' + verdict.textContent);
            TestCase.assertFalse(verdict.textContent.includes('HTTPS'), 'a secure page should not be told it is insecure');
        },

        async 'a blocked local stack is distinguished from a network problem'() {
            secureContext(true);
            // Present but refusing to construct, which is how a privacy
            // extension blocks WebRTC - and it must not be reported as a
            // network fault, since the network was never reached.
            globalThis.RTCPeerConnection = class {
                constructor() {
                    throw new Error('blocked');
                }
            };

            const verdict = await verdictAfterRun(panel());

            delete globalThis.RTCPeerConnection;

            TestCase.assertTrue(verdict.classList.contains('Failed'), 'a broken local stack cannot make calls');
            TestCase.assertTrue(verdict.textContent.includes('rules the network out'), 'the verdict should point at the browser, not the network, got: ' + verdict.textContent);
        },
    }
};
