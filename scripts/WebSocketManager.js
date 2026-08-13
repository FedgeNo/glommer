import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { Notification } from '/scripts/Notification.js';
import { Api } from '/scripts/Api.js';
import { Strings } from '/scripts/Strings.js';
import { list_in, list_item } from '/scripts/utils.js';

export class WebSocketManager {
    constructor() {
        this.socket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.reconnectDelay = 10000;
        this.token = null;
        this.reconnecting = false;
        // The page's own title, kept so the unread marker can be put in front of
        // it and taken back off. Remembered here rather than parked on the
        // <title> element, since it's this manager's bookkeeping.
        this.pageTitle = document.title;
        // NO this.init() here – init is called explicitly from main.js
    }

    init() {
        if (ClientConfig.get('currentUserId') === null) {
            return;
        }

        this.connect();

        const navLink = document.querySelector('.NotificationsNavLink');
        if (navLink && ClientConfig.get('currentUserId') !== null) {
            navLink.addEventListener('mouseenter', async () => {
                const dot = navLink.querySelector('.NotificationDot');
                if (!dot?.classList.contains('Active')) return;

                dot.classList.remove('Active');
                document.title = this.pageTitle;

                // Quiet: nobody asked for this, it happened because they moved
                // the mouse. If it fails the dot simply comes back, which says
                // everything a toast would and interrupts nothing.
                if (await Api.post('/api/mark-notifications-seen', undefined, { quiet: true }) === null) {
                    dot.classList.add('Active');
                }
            });
        }
    }

    async connect() {
        // Quiet: this reconnects on its own schedule and can fail a dozen
        // times while a laptop is asleep. A dozen toasts about a socket
        // nobody asked about is worse than the socket being down.
        const token = await Api.post('/api/ws-token', undefined, { quiet: true });

        if (token === null) {
            console.error('WebSocket token fetch failed');
            this.scheduleReconnect();

            return;
        }

        this.token = token.token;

        // Nothing in the address. A handshake is a GET and this API will not
        // set headers, so a token here could only ride in the URL - which is
        // the one part of a request that gets written down along the way. It
        // goes as the first message instead, inside the same encrypted channel
        // as everything after it, and the server tells nobody anything until
        // it has read one.
        const scheme = window.location.protocol === 'https:' ? 'wss' : 'ws';
        this.socket = new WebSocket(`${scheme}://${window.location.hostname}:${ClientConfig.wsPort()}/`);

        this.socket.addEventListener('open', () => {
            this.socket.send(this.token);
            this.reconnectAttempts = 0;

            const statusLine = document.querySelector('.WebSocketClientStatus');
            if (statusLine) {
                this.showStatus(statusLine);
            }
        });

        this.socket.addEventListener('message', (event) => {
            let data;
            try {
                data = JSON.parse(event.data);
            } catch (e) {
                return;
            }

            if (data.event === 'notification') {
                this.handleNotification(data.notification);
            } else if (data.event === 'message') {
                document.dispatchEvent(new CustomEvent('ws:message', { detail: data.message }));

                // Somewhere other than the conversations list, where opening
                // the page is what clears the mark - marking it read from
                // under the reader while they are elsewhere would lose it.
                if (!window.location.pathname.startsWith('/messages')) {
                    document.querySelectorAll('.MessageDot, .NavAlertDot').forEach(dot => dot.classList.add('Active'));
                }
            } else if (data.event === 'call') {
                document.dispatchEvent(new CustomEvent('ws:call', { detail: data.call }));
            }
        });

        this.socket.addEventListener('close', () => {
            // Only on a real change of state. Saying it on page load instead
            // would replace the line the server rendered - in the reader's own
            // language - with this one, before anything had happened.
            const statusLine = document.querySelector('.WebSocketClientStatus');

            if (statusLine) {
                this.showStatus(statusLine);
            }

            this.scheduleReconnect();
        });
        this.socket.addEventListener('error', () => this.socket?.close());
    }

    scheduleReconnect() {
        if (this.reconnecting) return;
        this.reconnecting = true;

        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            Toast.show('Something went wrong. Try reloading the page.');
            return;
        }

        this.reconnectAttempts += 1;
        setTimeout(() => {
            this.reconnecting = false;
            this.connect();
        }, this.reconnectDelay);
    }

    handleNotification(notificationData) {
        const notification = Notification.fromData(notificationData);

        const toastTarget = notification.targetURL();
        const toastContent = document.createElement(toastTarget === null ? 'span' : 'a');
        if (toastTarget !== null) toastContent.href = toastTarget;
        toastContent.textContent = notification.text();
        Toast.show(toastContent);

        const dropdownList = list_in(document.querySelector('.NotificationDropdown'), 'NotificationList RecentNotificationList d-flex flex-column gap-1');
        if (dropdownList) {
            const existing = dropdownList.querySelectorAll('.Notification');
            if (existing.length >= 5) {
                existing[existing.length - 1].closest('li').remove();
            }

            dropdownList.insertBeforeWithSpace(list_item(notification.toElement()), dropdownList.firstChild);
        }

        const pageList = list_in(document.querySelector('.NotificationsPage main'), 'NotificationList d-flex flex-column gap-1');
        if (pageList) {
            pageList.insertBeforeWithSpace(list_item(notification.toElement()), pageList.firstChild);
        }

        document.querySelectorAll('.NotificationDot, .NavAlertDot').forEach(dot => dot.classList.add('Active'));
        document.title = '🔴 ' + this.pageTitle;
    }

    // Keyed on WebSocketStatus, which is the element the server rendered and
    // the words this replaces: one entry says the line in both renderers.
    showStatus(statusLine) {
        const words = Strings.for('WebSocketStatus', {
            clientConnecting: 'Browser connection: Connecting…',
            clientConnected: 'Browser connection: Connected',
            clientDisconnecting: 'Browser connection: Disconnecting…',
            clientNotConnected: 'Browser connection: Not connected',
        });

        const states = {
            [WebSocket.CONNECTING]: words.clientConnecting,
            [WebSocket.OPEN]:       words.clientConnected,
            [WebSocket.CLOSING]:    words.clientDisconnecting,
            [WebSocket.CLOSED]:     words.clientNotConnected,
        };

        statusLine.textContent = (this.socket ? states[this.socket.readyState] : null) || words.clientNotConnected;

        if (this.socket?.readyState === WebSocket.OPEN) {
            statusLine.classList.remove('Error');
        } else {
            statusLine.classList.add('Error');
        }
    }
}
