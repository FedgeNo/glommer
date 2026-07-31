import { ClientConfig } from '/scripts/ClientConfig.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

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
        const results = document.querySelector('.VideoCallTestResults');
        results.replaceChildren();
        button.disabled = true;

        for (const step of VideoCallTestPanel.#steps()) {
            const line = VideoCallTestPanel.#lineFor(step.name);
            results.appendWithSpace(line);

            let outcome;

            try {
                outcome = await step.run();
            } catch (error) {
                outcome = { ok: false, detail: 'The check itself failed: ' + error.message };
            }

            VideoCallTestPanel.#settle(line, outcome);

            // A failed step invalidates everything after it - reporting those as
            // failures too would just be noise pointing away from the real cause.
            if (!outcome.ok) {
                results.appendWithSpace(VideoCallTestPanel.#note('Stopped here - the steps after this one depend on it.'));
                break;
            }
        }

        button.disabled = false;
    }

    static #steps() {
        return [
            {
                name: 'This page is a secure context',
                run: async () => window.isSecureContext
                    ? { ok: true, detail: 'Served over HTTPS, so the browser will allow a camera and a peer connection.' }
                    : { ok: false, detail: 'Not a secure context. Browsers refuse both the camera and WebRTC outside HTTPS, so no call can be set up from here.' },
            },
            {
                name: 'The browser supports WebRTC',
                run: async () => typeof RTCPeerConnection === 'function'
                    ? { ok: true, detail: 'RTCPeerConnection is available.' }
                    : { ok: false, detail: 'This browser has no RTCPeerConnection, so it cannot make or receive calls at all.' },
            },
            {
                name: 'A negotiation completes locally',
                run: () => VideoCallTestPanel.#loopback(),
            },
            {
                name: 'STUN is reachable from this network',
                run: () => VideoCallTestPanel.#stun(),
            },
            {
                name: 'The signalling endpoint answers',
                run: () => VideoCallTestPanel.#signalling(),
            },
        ];
    }

    /**
     * Two peer connections in this page, connected to each other with a data
     * channel and no network involved. It proves the browser's own stack works
     * before anything blames the network for a failure further along.
     */
    static async #loopback() {
        const caller = new RTCPeerConnection();
        const callee = new RTCPeerConnection();

        try {
            caller.onicecandidate = (event) => event.candidate && callee.addIceCandidate(event.candidate);
            callee.onicecandidate = (event) => event.candidate && caller.addIceCandidate(event.candidate);

            const opened = new Promise((resolve) => {
                const channel = caller.createDataChannel('check');
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
                ? { ok: true, detail: 'Two connections in this page negotiated and opened a data channel, so the WebRTC stack itself works.' }
                : { ok: false, detail: 'Two connections in this same page could not reach each other, which rules out the network - the browser\'s WebRTC stack is being blocked, most likely by an extension or a hardened privacy setting.' };
        } finally {
            caller.close();
            callee.close();
        }
    }

    /**
     * Whether STUN answers from here. A server-reflexive candidate is the
     * browser being told what its own address looks like from outside; without
     * one, two people behind different routers have no way to find each other.
     */
    static async #stun() {
        const ice_servers = window.iceServers ?? [];

        if (ice_servers.length === 0) {
            return { ok: false, detail: 'No STUN endpoint is configured, so calls can only work between people on the same network.' };
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
                ? { ok: true, detail: 'STUN answered and the browser learned its public address, so it can find a direct path to someone on another network.' }
                : { ok: false, detail: 'No reply from STUN - UDP to it is most likely blocked by a firewall. Calls will still work between two people on the same network, but not across the internet, and no call is ever relayed.' };
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
        const response = await fetch(ClientConfig.siteURL() + '/api/call-signal', {
            method: 'POST',
            headers: csrf_headers({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({
                otherUserId: ClientConfig.get('currentUserId'),
                type: 'hangup',
                signal: null,
            }),
        });

        if (response.status === 422) {
            return { ok: true, detail: 'The endpoint is reachable and accepted the request as authenticated before rejecting its content, which is everything a real signal needs.' };
        }

        if (response.status === 401 || response.status === 403) {
            return { ok: false, detail: 'The endpoint refused the request as unauthenticated (' + response.status + '). Signals between two real people would be refused the same way, so no call could be set up.' };
        }

        return { ok: false, detail: 'The endpoint answered ' + response.status + ', which is not what it should say to this request - something in front of it is interfering.' };
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
        detail.className = 'VideoCallTestDetail muted';
        detail.textContent = 'Checking…';
        line.appendWithSpace(detail);

        return line;
    }

    static #settle(line, outcome) {
        line.classList.add(outcome.ok ? 'Passed' : 'Failed');
        line.querySelector('.VideoCallTestDetail').textContent = outcome.detail;
    }

    static #note(text) {
        const note = document.createElement('li');
        note.className = 'VideoCallTestNote muted';
        note.textContent = text;

        return note;
    }
}

ReadyHandler.add(VideoCallTestPanel.init);
