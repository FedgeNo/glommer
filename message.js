class Message {
    messageId = null;
    senderId = null;
    recipientId = null;
    body = null;
    createdAt = null;
    element = null;

    static fromData(data) {
        const message = new Message();
        Object.assign(message, data);
        return message;
    }

    toElement() {
        const div = document.createElement('div');
        div.className = 'Message Card MountIn';

        if (Number(this.senderId) === Number(window.currentUserId)) {
            div.className += ' Own';
        }

        const meta = document.createElement('time');
        meta.className = 'Muted text-sm RelativeTime';
        meta.dateTime = parse_server_date(this.createdAt).toISOString();
        meta.textContent = format_relative_time(this.createdAt);
        div.appendChild(meta);

        const sender = (window.conversationUsers || {})[this.senderId];

        if (sender) {
            div.appendChild(this.senderHeader(sender, this.senderId));
        }

        // Body and (for other people's messages) the report button share one
        // row - text left, button hugging the right - so they never overlap.
        const line = document.createElement('div');
        line.className = 'MessageLine';

        const body = document.createElement('p');
        body.textContent = this.body;
        line.appendChild(body);

        // No report button on the admin's messages - the API rejects reports
        // about the admin, since nobody could act on one anyway.
        if (window.currentUserId !== null && Number(this.senderId) !== Number(window.currentUserId) && Number(this.senderId) !== 1) {
            const report_button = document.createElement('button');
            report_button.type = 'button';
            report_button.className = 'Btn ReportButton';
            report_button.dataset.targetType = 'message';
            report_button.dataset.targetId = this.messageId;
            report_button.textContent = 'Report';
            line.appendChild(report_button);
        }

        div.appendChild(line);

        this.element = div;

        return div;
    }

    senderHeader(sender, sender_id) {
        return User.fromData({ userId: sender_id, ...sender }).header();
    }
}

/**
 * Live-appends a message pushed over the WebSocket connection (see main.js's
 * connect_websocket()) - but only if the conversation it belongs to is the
 * one currently open, since a page has no DOM to append into for any other
 * conversation. Messages for a conversation that isn't open still surface
 * via the normal 'message' notification toast.
 */
document.addEventListener('ws:message', (event) => {
    const data = event.detail;
    const list = document.querySelector('.MessageList');

    if (!list || Number(list.dataset.otherUserId) !== Number(data.senderId)) {
        return;
    }

    // Only follow along if the reader was already at the bottom - otherwise
    // a new message would yank them away from history they've scrolled up to read.
    const was_near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    // ItemList wraps every child in its own <li>, so the empty-state .Notice
    // is a grandchild of the list, not a direct child.
    const placeholder = list.querySelector('.Notice');

    if (placeholder) {
        placeholder.closest('li').remove();
    }

    const message = Message.fromData(data);
    const element = message.toElement();
    list.appendChild(list_item(element));
    render_math(element);

    if (was_near_bottom) {
        window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
    }
});
