import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { csrf_headers } from '/scripts/utils.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * One-to-one video calling inside a message thread.
 *
 * Nothing is offered until a direct path between the two browsers has been
 * proven, because there is no relay to fall back on: while both people have the
 * thread open, one side quietly opens a data-channel-only connection to the
 * other. That needs no camera and shows nothing, and only if it connects does a
 * call button appear.
 *
 * Candidates are gathered to completion before an offer is sent rather than
 * trickled, so one message each way sets up a call and the server relays a
 * handful of requests instead of a stream of them.
 *
 * Presence decides whether a call can be STARTED. It has no say once one is
 * running: the call's own connection is the liveness signal, and leaving the
 * page tears it down, which the other side sees directly.
 */
export class VideoCall {
    /** Comfortably inside ChatPresence::PRESENCE_SECONDS, so one lost request is survivable. */
    static #PRESENCE_INTERVAL_MS = 10000;

    /** How long to wait for ICE gathering before giving up on a negotiation. */
    static #GATHER_TIMEOUT_MS = 5000;

    static #otherUserId = null;
    static #list = null;
    static #composer = null;
    static #presenceTimer = null;

    /** Set once a data-channel probe has actually connected these two browsers. */
    static #pathProven = false;
    static #probe = null;

    static #connection = null;
    static #localStream = null;
    static #stage = null;
    static #panel = null;
    static #callButton = null;
    static #offer = null;

    static init() {
        VideoCall.#list = document.querySelector('.MessageList[data-other-user-id]');

        if (!VideoCall.#list || ClientConfig.get('currentUserId') === null) {
            return;
        }

        VideoCall.#otherUserId = Number(VideoCall.#list.dataset.otherUserId);
        VideoCall.#composer = document.querySelector('.MessageComposer');

        document.addEventListener('ws:call', (event) => VideoCall.#receive(event.detail));
        document.addEventListener('click', (event) => VideoCall.#onClick(event));

        // Leaving ends any call outright, and drops the heartbeat so the other
        // side stops being offered a call to someone who has gone.
        window.addEventListener('pagehide', () => {
            VideoCall.#hangUp(false);
            VideoCall.#post('/api/chat-presence', { otherUserId: VideoCall.#otherUserId, leaving: true });
        });

        VideoCall.#beat();
        VideoCall.#presenceTimer = setInterval(() => VideoCall.#beat(), VideoCall.#PRESENCE_INTERVAL_MS);
    }

    // ----------------------------------------------------------------
    // Presence, and the silent probe it triggers
    // ----------------------------------------------------------------

    static async #beat() {
        const result = await VideoCall.#post('/api/chat-presence', { otherUserId: VideoCall.#otherUserId });

        if (result === null) {
            return;
        }

        if (!result.otherUserPresent) {
            VideoCall.#showCallButton(false);

            return;
        }

        if (VideoCall.#pathProven) {
            VideoCall.#showCallButton(true);
        } else if (VideoCall.#probe === null && VideoCall.#initiates()) {
            VideoCall.#openProbe();
        }
    }

    /**
     * Both browsers run this same code, so without a rule they would both offer
     * a probe and neither answer. The lower id going first is arbitrary but
     * agreed - it mirrors VideoCall::initiates() on the server.
     */
    static #initiates() {
        return Number(ClientConfig.get('currentUserId')) < VideoCall.#otherUserId;
    }

    static #peerConnection() {
        return new RTCPeerConnection({ iceServers: window.iceServers ?? [] });
    }

    /** The probe carries no media at all - it exists only to prove a path. */
    static async #openProbe() {
        VideoCall.#probe = VideoCall.#peerConnection();
        VideoCall.#probe.createDataChannel('path');
        VideoCall.#watchProbe(VideoCall.#probe);

        const offer = await VideoCall.#describe(VideoCall.#probe, () => VideoCall.#probe.createOffer());

        VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'probeOffer', signal: offer });
    }

    static async #answerProbe(offer) {
        VideoCall.#probe = VideoCall.#peerConnection();
        VideoCall.#watchProbe(VideoCall.#probe);

        await VideoCall.#probe.setRemoteDescription(offer);
        const answer = await VideoCall.#describe(VideoCall.#probe, () => VideoCall.#probe.createAnswer());

        VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'probeAnswer', signal: answer });
    }

    /**
     * A probe that connects has answered its only question, so it is closed
     * again straight away rather than held open for the life of the page.
     */
    static #watchProbe(connection) {
        connection.onconnectionstatechange = () => {
            if (connection.connectionState === 'connected') {
                VideoCall.#pathProven = true;
                VideoCall.#showCallButton(true);
                connection.close();
                VideoCall.#probe = null;
            } else if (connection.connectionState === 'failed') {
                // No direct path, and there is no relay by design - so no call
                // is offered rather than one being proxied.
                connection.close();
                VideoCall.#probe = null;
            }
        };
    }

    /**
     * Applies a description and waits for gathering to finish, so the result
     * carries every candidate and no follow-up messages are needed.
     */
    static async #describe(connection, create) {
        await connection.setLocalDescription(await create());

        if (connection.iceGatheringState !== 'complete') {
            await new Promise((resolve) => {
                const done = () => {
                    if (connection.iceGatheringState === 'complete') {
                        connection.removeEventListener('icegatheringstatechange', done);
                        resolve();
                    }
                };

                connection.addEventListener('icegatheringstatechange', done);
                setTimeout(resolve, VideoCall.#GATHER_TIMEOUT_MS);
            });
        }

        return connection.localDescription;
    }

    // ----------------------------------------------------------------
    // The call
    // ----------------------------------------------------------------

    static async #call() {
        if (!await VideoCall.#openCamera()) {
            return;
        }

        VideoCall.#showStage('Calling…', 'Cancel');
        VideoCall.#connection = VideoCall.#buildCall();

        const offer = await VideoCall.#describe(VideoCall.#connection, () => VideoCall.#connection.createOffer());

        VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'offer', signal: offer });
    }

    static async #accept() {
        if (!await VideoCall.#openCamera()) {
            VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'decline', signal: null });

            return;
        }

        VideoCall.#showStage('Connecting…', 'End call');
        VideoCall.#connection = VideoCall.#buildCall();

        await VideoCall.#connection.setRemoteDescription(VideoCall.#offer);
        VideoCall.#offer = null;

        const answer = await VideoCall.#describe(VideoCall.#connection, () => VideoCall.#connection.createAnswer());

        VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'answer', signal: answer });
    }

    static #buildCall() {
        const connection = VideoCall.#peerConnection();

        VideoCall.#localStream.getTracks().forEach((track) => connection.addTrack(track, VideoCall.#localStream));

        connection.ontrack = (event) => {
            VideoCall.#stage.querySelector('.VideoCallRemote').srcObject = event.streams[0];
        };

        connection.onconnectionstatechange = () => {
            if (connection.connectionState === 'connected') {
                VideoCall.#setStatus('Connected');
                VideoCall.#setEndLabel('End call');
            } else if (['failed', 'disconnected', 'closed'].includes(connection.connectionState)) {
                // Includes the other person simply leaving the page - their side
                // goes away and this is how it is noticed.
                VideoCall.#hangUp(false);
            }
        };

        return connection;
    }

    static async #openCamera() {
        try {
            VideoCall.#localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });

            return true;
        } catch (error) {
            Toast.show('Could not use your camera or microphone. Check the browser\'s permission for this site.');

            return false;
        }
    }

    /**
     * Ends whatever is running and puts the thread back. Tells the other side
     * unless this was triggered BY the other side, or by the page going away.
     */
    static #hangUp(announce = true) {
        if (announce && (VideoCall.#connection !== null || VideoCall.#offer !== null)) {
            VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'hangup', signal: null });
        }

        VideoCall.#connection?.close();
        VideoCall.#connection = null;
        VideoCall.#offer = null;

        VideoCall.#localStream?.getTracks().forEach((track) => track.stop());
        VideoCall.#localStream = null;

        VideoCall.#hideStage();
    }

    // ----------------------------------------------------------------
    // Signals in
    // ----------------------------------------------------------------

    static async #receive(call) {
        if (Number(call.fromUserId) !== VideoCall.#otherUserId) {
            return;
        }

        if (call.type === 'probeOffer') {
            VideoCall.#answerProbe(call.signal);
        } else if (call.type === 'probeAnswer') {
            VideoCall.#probe?.setRemoteDescription(call.signal);
        } else if (call.type === 'offer') {
            VideoCall.#offer = call.signal;
            VideoCall.#showIncoming();
        } else if (call.type === 'answer') {
            VideoCall.#connection?.setRemoteDescription(call.signal);
        } else if (call.type === 'decline') {
            Toast.show('Your call was declined.');
            VideoCall.#hangUp(false);
        } else if (call.type === 'hangup') {
            VideoCall.#hangUp(false);
        }
    }

    // ----------------------------------------------------------------
    // What the thread looks like during all this
    // ----------------------------------------------------------------

    static #onClick(event) {
        if (event.target.closest('.VideoCallStartButton')) {
            VideoCall.#call();
        } else if (event.target.closest('.VideoCallAcceptButton')) {
            VideoCall.#accept();
        } else if (event.target.closest('.VideoCallDeclineButton')) {
            VideoCall.#post('/api/call-signal', { otherUserId: VideoCall.#otherUserId, type: 'decline', signal: null });
            VideoCall.#offer = null;
            VideoCall.#hideStage();
        } else if (event.target.closest('.VideoCallEndButton')) {
            VideoCall.#hangUp();
        }
    }

    static #showCallButton(show) {
        if (!show) {
            VideoCall.#callButton?.remove();
            VideoCall.#callButton = null;

            return;
        }

        if (VideoCall.#callButton !== null || VideoCall.#composer === null || VideoCall.#connection !== null) {
            return;
        }

        VideoCall.#callButton = document.createElement('button');
        VideoCall.#callButton.type = 'button';
        VideoCall.#callButton.className = 'VideoCallStartButton Button';
        VideoCall.#callButton.textContent = 'Video call';
        VideoCall.#composer.appendWithSpace(VideoCall.#callButton);
    }

    /** The call takes the thread's place - the messages are still there behind it. */
    static #showStage(status, end_label) {
        VideoCall.#showCallButton(false);
        VideoCall.#stopBeating();

        VideoCall.#stage = document.createElement('div');
        VideoCall.#stage.className = 'VideoCallStage';

        const remote = document.createElement('video');
        remote.className = 'VideoCallRemote';
        remote.autoplay = true;
        remote.playsInline = true;
        VideoCall.#stage.appendWithSpace(remote);

        const local = document.createElement('video');
        local.className = 'VideoCallLocal';
        local.autoplay = true;
        local.playsInline = true;
        local.muted = true;
        local.srcObject = VideoCall.#localStream;
        VideoCall.#stage.appendWithSpace(local);

        VideoCall.#list.hidden = true;
        VideoCall.#list.after(VideoCall.#stage);

        VideoCall.#showPanel(status, end_label);
    }

    /** The composer's place is taken by what the call is doing, and a way out. */
    static #showPanel(status, end_label) {
        VideoCall.#panel?.remove();

        VideoCall.#panel = document.createElement('div');
        VideoCall.#panel.className = 'VideoCallPanel d-flex align-items-center gap-2';

        const text = document.createElement('span');
        text.className = 'VideoCallStatus';
        text.textContent = status;
        VideoCall.#panel.appendWithSpace(text);

        if (end_label !== null) {
            const end = document.createElement('button');
            end.type = 'button';
            end.className = 'VideoCallEndButton Button ms-auto';
            end.textContent = end_label;
            VideoCall.#panel.appendWithSpace(end);
        }

        if (VideoCall.#composer !== null) {
            VideoCall.#composer.hidden = true;
            VideoCall.#composer.after(VideoCall.#panel);
        }
    }

    /** An offer arriving is the one case with no stage yet - just a choice. */
    static #showIncoming() {
        VideoCall.#showCallButton(false);
        VideoCall.#showPanel('Incoming video call', null);

        const accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'VideoCallAcceptButton Button ms-auto';
        accept.textContent = 'Accept';
        VideoCall.#panel.appendWithSpace(accept);

        const decline = document.createElement('button');
        decline.type = 'button';
        decline.className = 'VideoCallDeclineButton Button';
        decline.textContent = 'Decline';
        VideoCall.#panel.appendWithSpace(decline);
    }

    static #setStatus(text) {
        const status = VideoCall.#panel?.querySelector('.VideoCallStatus');

        if (status) {
            status.textContent = text;
        }
    }

    static #setEndLabel(label) {
        const end = VideoCall.#panel?.querySelector('.VideoCallEndButton');

        if (end) {
            end.textContent = label;
        }
    }

    static #hideStage() {
        VideoCall.#stage?.remove();
        VideoCall.#stage = null;

        VideoCall.#panel?.remove();
        VideoCall.#panel = null;

        VideoCall.#list.hidden = false;

        if (VideoCall.#composer !== null) {
            VideoCall.#composer.hidden = false;
        }

        VideoCall.#startBeating();
    }

    static #stopBeating() {
        clearInterval(VideoCall.#presenceTimer);
        VideoCall.#presenceTimer = null;
    }

    static #startBeating() {
        if (VideoCall.#presenceTimer === null) {
            VideoCall.#beat();
            VideoCall.#presenceTimer = setInterval(() => VideoCall.#beat(), VideoCall.#PRESENCE_INTERVAL_MS);
        }
    }

    /** Signals are ordinary requests - the WebSocket daemon carries only the reply. */
    static async #post(path, body) {
        try {
            const response = await fetch(ClientConfig.siteURL() + path, {
                method: 'POST',
                headers: csrf_headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(body),
                keepalive: true,
            });

            if (!response.ok) {
                return null;
            }

            return (await response.json()).response;
        } catch (error) {
            return null;
        }
    }
}

ReadyHandler.add(VideoCall.init);
