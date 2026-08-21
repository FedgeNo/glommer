import { ClientConfig } from '/scripts/ClientConfig.js';
import { Strings } from '/scripts/Strings.js';
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';

/**
 * Mirrors VideoCallTestPanel.php: runs call setup for real, one step at a time,
 * and says which step failed and what that means for calls.
 *
 * Every step is written so a failure names a cause rather than a symptom - the
 * point of the page is that "no call button appeared" has several completely
 * different fixes behind it.
 */
export class VideoCallTestPanel {
    /** How long any single step may take before it counts as hung. */
    static #STEP_TIMEOUT_MS = 8000;

    static init() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('.VideoCallTestButton');

            if (button) {
                VideoCallTestPanel.#run(button);
            }
        });
    }

    static async #run(button) {
        const words = Strings.for('VideoCallTestPanel');
        const results = document.querySelector('.VideoCallTestResults');
        const verdict = document.querySelector('.VideoCallTestVerdict');
        results.replaceChildren();
        verdict.replaceChildren();
        verdict.className = 'VideoCallTestVerdict';
        Working.start(button);

        // Which step stopped the run, if one did - the verdict is a reading of
        // that rather than of a pass count, since the steps do not all mean the
        // same thing for whether a call can happen.
        let stopped_at = null;

        for (const step of VideoCallTestPanel.#steps()) {
            const line = VideoCallTestPanel.#lineFor(step.name);
            results.appendWithSpace(line);

            let outcome;

            try {
                outcome = await step.run();
            } catch (error) {
                outcome = { ok: false, detail: (words.checkFailed || '').replace('{error}', error.message) };
            }

            VideoCallTestPanel.#settle(line, outcome);

            // A failed step invalidates everything after it - reporting those as
            // failures too would just be noise pointing away from the real cause.
            if (!outcome.ok) {
                results.appendWithSpace(VideoCallTestPanel.#note(words.stopped || ''));
                stopped_at = step.id;
                break;
            }
        }

        VideoCallTestPanel.#declare(verdict, stopped_at);

        Working.stop(button);
    }

    static #steps() {
        const words = Strings.for('VideoCallTestPanel');
        return [
            {
                id: 'secure',
                name: words.secureName || '',
                run: async () => window.isSecureContext
                    ? { ok: true, detail: words.securePass || '' }
                    : { ok: false, detail: words.secureFail || '' },
            },
            {
                id: 'webrtc',
                name: words.webrtcName || '',
                run: async () => typeof RTCPeerConnection === 'function'
                    ? { ok: true, detail: words.webrtcPass || '' }
                    : { ok: false, detail: words.webrtcFail || '' },
            },
            {
                id: 'loopback',
                name: words.loopbackName || '',
                run: () => VideoCallTestPanel.#loopback(),
            },
            {
                id: 'stun',
                name: words.stunName || '',
                run: () => VideoCallTestPanel.#stun(),
            },
            {
                id: 'signalling',
                name: words.signallingName || '',
                run: () => VideoCallTestPanel.#signalling(),
            },
        ];
    }

    /**
     * The one line the page exists to produce. Only the STUN failure is a
     * partial - it costs calls across the internet while leaving them working
     * between two people on the same network, which is a real and quite
     * different answer from "no".
     */
    static #declare(verdict, stopped_at) {
        const words = Strings.for('VideoCallTestPanel');
        const outcomes = {
            secure: { state: 'Failed', text: words.secureVerdict },
            webrtc: { state: 'Failed', text: words.webrtcVerdict },
            loopback: { state: 'Failed', text: words.loopbackVerdict },
            stun: { state: 'Partial', text: words.stunVerdict },
            signalling: { state: 'Failed', text: words.signallingVerdict },
        };
        const outcome = outcomes[stopped_at] ?? { state: 'Passed', text: words.passVerdict };

        verdict.className = 'VideoCallTestVerdict ' + outcome.state;
        verdict.textContent = outcome.text;
    }

    /**
     * Two peer connections in this page, connected to each other with a data
     * channel and no network involved. It proves the browser's own stack works
     * before anything blames the network for a failure further along.
     */
    static async #loopback() {
        const words = Strings.for('VideoCallTestPanel');
        const caller = new RTCPeerConnection();
        const callee = new RTCPeerConnection();

        try {
            caller.onicecandidate = (event) => event.candidate && callee.addIceCandidate(event.candidate);
            callee.onicecandidate = (event) => event.candidate && caller.addIceCandidate(event.candidate);

            // The channel is opened outside the executor on purpose: a stack
            // that is present but unusable throws here, and inside the executor
            // that throw becomes a rejected promise nothing is waiting on yet -
            // an unhandled rejection instead of the failure this step exists to
            // report.
            const channel = caller.createDataChannel('check');

            const opened = new Promise((resolve) => {
                channel.onopen = () => resolve(true);
            });

            const offer = await caller.createOffer();
            await caller.setLocalDescription(offer);
            await callee.setRemoteDescription(offer);

            const answer = await callee.createAnswer();
            await callee.setLocalDescription(answer);
            await caller.setRemoteDescription(answer);

            const connected = await VideoCallTestPanel.#within(opened);

            return connected
                ? { ok: true, detail: words.loopbackPass || '' }
                : { ok: false, detail: words.loopbackFail || '' };
        } finally {
            caller.close();
            callee.close();
        }
    }

    /**
     * The ICE configuration a real call would use, carried on the panel by
     * VideoCallTestPanel.php.
     */
    static #iceServers() {
        const panel = document.querySelector('.VideoCallTestPanel');

        return panel === null ? [] : (JSON.parse(panel.dataset.iceServers ?? '[]') ?? []);
    }

    /**
     * Whether STUN answers from here. A server-reflexive candidate is the
     * browser being told what its own address looks like from outside; without
     * one, two people behind different routers have no way to find each other.
     */
    static async #stun() {
        const words = Strings.for('VideoCallTestPanel');
        const ice_servers = VideoCallTestPanel.#iceServers();

        if (ice_servers.length === 0) {
            return { ok: false, detail: words.noStun || '' };
        }

        const connection = new RTCPeerConnection({ iceServers: ice_servers });

        try {
            const reflexive = new Promise((resolve) => {
                connection.onicecandidate = (event) => {
                    if (event.candidate === null) {
                        resolve(false);
                    } else if (event.candidate.type === 'srflx') {
                        resolve(true);
                    }
                };
            });

            connection.createDataChannel('check');
            await connection.setLocalDescription(await connection.createOffer());

            const found = await VideoCallTestPanel.#within(reflexive);

            return found
                ? { ok: true, detail: words.stunPass || '' }
                : { ok: false, detail: words.stunFail || '' };
        } finally {
            connection.close();
        }
    }

    /**
     * That the signalling path is reachable, authenticated and passes CSRF. It
     * deliberately addresses a call to the admin themselves, which the endpoint
     * refuses - reaching that refusal is what proves everything in front of it
     * worked, and nothing is left behind.
     */
    static async #signalling() {
        const words = Strings.for('VideoCallTestPanel');
        // request() rather than post(): the status IS the result here, and a
        // refusal is what this step is hoping for rather than something to
        // announce.
        const { status } = await Api.request('/api/call-signal', {
            otherUserId: ClientConfig.get('currentUserId'),
            type: 'hangup',
            signal: null,
        });

        if (status === 422) {
            return { ok: true, detail: words.signallingPass || '' };
        }

        if (status === 401 || status === 403) {
            return { ok: false, detail: (words.signallingAuthFail || '').replace('{status}', String(status)) };
        }

        return { ok: false, detail: (words.signallingUnexpected || '').replace('{status}', String(status)) };
    }

    /** Resolves false if the promise has not settled before the step timeout. */
    static #within(promise) {
        return Promise.race([
            promise,
            new Promise((resolve) => setTimeout(() => resolve(false), VideoCallTestPanel.#STEP_TIMEOUT_MS)),
        ]);
    }

    static #lineFor(name) {
        const line = document.createElement('li');
        line.className = 'VideoCallTestStep';

        const label = document.createElement('strong');
        label.textContent = name;
        line.appendWithSpace(label);

        const detail = document.createElement('div');
        detail.className = 'VideoCallTestDetail';
        detail.textContent = Strings.for('MiscellaneousClient').checking || '';
        line.appendWithSpace(detail);

        return line;
    }

    static #settle(line, outcome) {
        line.classList.add(outcome.ok ? 'Passed' : 'Failed');
        line.querySelector('.VideoCallTestDetail').textContent = outcome.detail;
    }

    static #note(text) {
        const note = document.createElement('li');
        note.className = 'VideoCallTestNote';
        note.textContent = text;

        return note;
    }
}

ReadyHandler.add(VideoCallTestPanel.init);
