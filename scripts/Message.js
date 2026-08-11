import { expand } from '/scripts/EmojiShortcode.js';
import { User } from '/scripts/User.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { MessageCrypto } from '/scripts/MessageCrypto.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { parse_server_date, list_in, list_item } from '/scripts/utils.js';
import { render_math } from '/scripts/MathRenderer.js';
import { EmojiRenderer } from '/scripts/EmojiRenderer.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Strings } from '/scripts/Strings.js';

export class Message {
    messageId = null;
    senderId = null;
    recipientId = null;
    body = null;
    bodyCiphertext = null;
    createdAt = null;
    sender = null;
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

        if (this.sender) {
            byline.appendWithSpace(this.senderHeader(this.sender, this.senderId));
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

        if (this.bodyCiphertext !== null) {
            div.className += ' Encrypted Locked';
            div.dataset.cipherEnvelope = this.bodyCiphertext;
            div.dataset.messageId = this.messageId;
            body.textContent = Strings.for('Message', { encrypted: 'Encrypted message' }).encrypted;
        } else {
            body.textContent = expand(this.body);
        }

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

        // The written line only - the byline and the timestamp beside it are
        // not somebody's writing.
        this.element.querySelectorAll(EmojiRenderer.CONTENT).forEach(content => EmojiRenderer.render(content));

        const messageBody = div.querySelector('.MessageLine p');
        if (messageBody && EmojiRenderer.isEmojiOnly(messageBody)) {
            div.classList.add('emoji-only');
        }

        if (this.bodyCiphertext !== null) {
            Message.decryptInto(div);
        }

        return div;
    }

    senderHeader(sender, sender_id) {
        return User.fromData({ userId: sender_id, ...sender }).header();
    }

    /**
     * Opens one rendered message's envelope in place, once the thread key is
     * available (MessageUnlockForm.js). Registering the envelope first is what
     * lets a report of this message reveal its key later - see ReportButton.js.
     */
    static async decryptInto(article) {
        const envelope = article.dataset.cipherEnvelope;
        if (!envelope) return;

        MessageCrypto.rememberEnvelope(article.dataset.messageId, envelope);

        const thread_key = MessageCrypto.threadKey();
        if (thread_key === null) return;

        const body = article.querySelector('.MessageLine p');
        const text = await MessageCrypto.decrypt(thread_key, envelope);

        if (text === null) {
            // An envelope the current keys don't open - sent under keys that
            // have since been reset. Honest and final; nothing can read it now.
            body.textContent = Strings.for('Message', {
                decryptionFailed: 'This message was encrypted with keys that no longer exist.',
            }).decryptionFailed;
            return;
        }

        body.textContent = expand(text);
        article.classList.remove('Locked');

        EmojiRenderer.render(body);
        if (EmojiRenderer.isEmojiOnly(body)) {
            article.classList.add('emoji-only');
        }
        render_math(article);
    }

    /**
     * Scroll the message list to the bottom on page load.
     */
    static init() {
        if (document.querySelector('.MessageComposer')) {
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

    // Who this thread is with, read off the composer: a thread nobody has
    // written in yet has no list to ask.
    const recipient = document.querySelector('.MessageComposer [name="recipientId"]');

    if (!recipient || Number(recipient.value) !== Number(data.senderId)) {
        return;
    }

    const was_near_bottom = window.innerHeight + window.scrollY >= document.body.scrollHeight - 150;
    const list = list_in(document.querySelector('main'), 'MessageList d-flex flex-column');

    if (!list) return;

    const message = Message.fromData(data);
    const element = message.toElement();
    list.appendWithSpace(list_item(element));
    render_math(element);

    if (was_near_bottom) {
        window.scrollTo({ top: document.body.scrollHeight, left: 0, behavior: 'instant' });
    }
});

ReadyHandler.add(Message.init);

