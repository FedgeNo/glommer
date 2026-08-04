import { expand } from '/scripts/EmojiShortcode.js';
import { User } from '/scripts/User.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { parse_server_date, list_item } from '/scripts/utils.js';
import { render_math } from '/scripts/MathRenderer.js';
import { EmojiRenderer } from '/scripts/EmojiRenderer.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class Message {
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
        const div = document.createElement('article');
        div.className = 'Message MountIn';

        if (Number(this.senderId) === Number(ClientConfig.get('currentUserId'))) {
            div.className += ' Own';
        }

        const byline = document.createElement('div');
        byline.className = 'MessageByline d-flex align-items-start gap-2';

        const sender = (ClientConfig.get('conversationUsers') || {})[this.senderId];
        if (sender) {
            byline.appendWithSpace(this.senderHeader(sender, this.senderId));
        }

        const meta = document.createElement('time');
        meta.className = 'muted text-sm RelativeTime';
        meta.dateTime = parse_server_date(this.createdAt).toISOString();
        meta.textContent = RelativeTime.format(this.createdAt);
        byline.appendWithSpace(meta);

        div.appendWithSpace(byline);

        const line = document.createElement('div');
        line.className = 'MessageLine';

        const body = document.createElement('p');
        body.textContent = expand(this.body);
        line.appendWithSpace(body);

        if (ClientConfig.get('currentUserId') !== null
            && Number(this.senderId) !== Number(ClientConfig.get('currentUserId'))
            && Number(this.senderId) !== 1) {
            const report_button = document.createElement('button');
            report_button.type = 'button';
            report_button.className = 'Button ReportButton';
            report_button.dataset.targetType = 'message';
            report_button.dataset.targetId = this.messageId;
            report_button.textContent = 'Report';
            line.appendWithSpace(report_button);
        }

        div.appendWithSpace(line);

        this.element = div;

        EmojiRenderer.render(this.element);

        const messageBody = div.querySelector('.MessageLine p');
        if (messageBody && EmojiRenderer.isEmojiOnly(messageBody)) {
            div.classList.add('emoji-only');
        }

        return div;
    }

    senderHeader(sender, sender_id) {
        return User.fromData({ userId: sender_id, ...sender }).header();
    }

    /**
     * Scroll the message list to the bottom on page load.
     */
    static init() {
        if (document.querySelector('.MessageList')) {
            window.addEventListener('load', () => {
                window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
                const composerTextarea = document.querySelector('.MessageComposer textarea');
                if (composerTextarea) composerTextarea.focus();
            });
        }
    }
}

document.addEventListener('ws:message', (event) => {
    const data = event.detail;
    const list = document.querySelector('.MessageList');

    if (!list || Number(list.dataset.otherUserId) !== Number(data.senderId)) {
        return;
    }

    const was_near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;

    const placeholder = list.querySelector('.Notice');
    if (placeholder) {
        placeholder.closest('li').remove();
    }

    const message = Message.fromData(data);
    const element = message.toElement();
    list.appendWithSpace(list_item(element));
    render_math(element);

    if (was_near_bottom) {
        window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
    }
});

ReadyHandler.add(Message.init);

