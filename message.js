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

        const sender = (window.conversationUsers || {})[this.senderId];

        if (sender) {
            div.appendChild(this.senderHeader(sender, this.senderId));
        }

        const body = document.createElement('p');
        body.textContent = this.body;
        div.appendChild(body);

        const meta = document.createElement('time');
        meta.className = 'Muted text-sm RelativeTime';
        meta.dateTime = parse_server_date(this.createdAt).toISOString();
        meta.textContent = format_relative_time(this.createdAt);
        div.appendChild(meta);

        if (window.currentUserId !== null && Number(this.senderId) !== Number(window.currentUserId)) {
            const report_button = document.createElement('button');
            report_button.type = 'button';
            report_button.className = 'Btn ReportButton';
            report_button.dataset.targetType = 'message';
            report_button.dataset.targetId = this.messageId;
            report_button.textContent = 'Report';
            div.appendChild(report_button);
        }

        this.element = div;

        return div;
    }

    senderHeader(sender, sender_id) {
        const header = document.createElement('div');
        header.className = 'd-flex align-items-center gap-3';

        const name = sender.displayName || sender.username;

        header.appendChild(avatar_element(Boolean(sender.image), sender.image, name, sender_id, false));

        const info = document.createElement('div');

        const name_line = document.createElement('div');
        name_line.className = 'fw-semibold';
        name_line.textContent = name;
        info.appendChild(name_line);

        const username_line = document.createElement('div');
        username_line.className = 'Muted text-sm';
        username_line.textContent = '@' + sender.username;
        info.appendChild(username_line);

        header.appendChild(info);

        return header;
    }
}
