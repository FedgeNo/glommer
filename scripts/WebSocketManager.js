import { ClientConfig } from '/scripts/ClientConfig.js';
import { Toast } from '/scripts/Toast.js';
import { Notification } from '/scripts/Notification.js';
import { csrf_headers, list_item } from '/scripts/utils.js';

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
                try {
                    const response = await fetch(`${ClientConfig.siteURL()}/api/mark-notifications-seen`, {
                        method: 'POST',
                        headers: csrf_headers(),
                    });
                    if (!response.ok) {
                        dot.classList.add('Active');
                    }
                } catch (error) {
                    dot.classList.add('Active');
                }
            });
        }
    }

    async connect() {
        try {
            const response = await fetch(`${ClientConfig.siteURL()}/api/ws-token`, {
                method: 'POST',
                headers: csrf_headers(),
            });

            if (!response.ok) throw new Error(`token fetch failed (${response.status})`);
            const json = await response.json();
            this.token = json.response.token;
        } catch (error) {
            console.error('WebSocket token fetch error:', error);
            this.scheduleReconnect();
            return;
        }

        const scheme = window.location.protocol === 'https:' ? 'wss' : 'ws';
        this.socket = new WebSocket(`${scheme}://${window.location.hostname}:${ClientConfig.wsPort()}/?token=${encodeURIComponent(this.token)}`);

        this.socket.addEventListener('open', () => {
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

        this.socket.addEventListener('close', () => this.scheduleReconnect());
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

        const dropdownList = document.querySelector('.NotificationDropdown .NotificationList');
        if (dropdownList) {
            const placeholder = dropdownList.querySelector('.Notice');
            if (placeholder) placeholder.closest('li').remove();

            const existing = dropdownList.querySelectorAll('.Notification');
            if (existing.length >= 5) {
                existing[existing.length - 1].closest('li').remove();
            }

            dropdownList.insertBeforeWithSpace(list_item(notification.toElement()), dropdownList.firstChild);
        }

        const pageList = Array.from(document.querySelectorAll('.NotificationList'))
            .find(list => !list.closest('.NotificationDropdown'));
        if (pageList) {
            const placeholder = pageList.querySelector('.Notice');
            if (placeholder) placeholder.closest('li').remove();
            pageList.insertBeforeWithSpace(list_item(notification.toElement()), pageList.firstChild);
        }

        document.querySelectorAll('.NotificationDot, .NavAlertDot').forEach(dot => dot.classList.add('Active'));
        document.title = '🔴 ' + this.pageTitle;
    }

    showStatus(statusLine) {
        const states = {
            [WebSocket.CONNECTING]: 'Connecting…',
            [WebSocket.OPEN]:       'Connected',
            [WebSocket.CLOSING]:    'Disconnecting…',
            [WebSocket.CLOSED]:     'Not connected',
        };

        const stateText = this.socket ? states[this.socket.readyState] || 'Unknown' : 'Not connected';
        statusLine.textContent = `Browser connection: ${stateText}`;

        if (this.socket?.readyState === WebSocket.OPEN) {
            statusLine.classList.remove('Error');
        } else {
            statusLine.classList.add('Error');
        }
    }
}
