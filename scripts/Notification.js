import { ClientConfig } from '/scripts/ClientConfig.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { parse_server_date } from '/scripts/utils.js';
import { Avatar } from '/scripts/Avatar.js';
import { Strings } from '/scripts/Strings.js';

export class Notification {
    notificationId = null;
    userId = null;
    type = null;
    postId = null;
    createdAt = null;
    actor = null;
    element = null;

    static fromData(data) {
        const notification = new Notification();
        Object.assign(notification, data);
        return notification;
    }

    /**
     * Mirrors Notification::nameFor - the name and always the username too,
     * since a display name is neither unique nor the account's own. The
     * username is written with its @ wherever it is shown.
     */
    actorName() {
        const slug = this.actor.slug || '';
        const handle = '@' + slug;
        const name = this.actor.title || '';

        if (name === '' || name === slug) {
            return handle;
        }

        return name + ' (' + handle + ')';
    }

    /** Mirrors Notification::textFor() - see MessagingExtras.php for the words. */
    text() {
        const words = Strings.for('Notification');

        const phrase = words[this.type] ?? words.default;

        return phrase.replace('{name}', this.actorName());
    }

    targetURL() {
        switch (this.type) {
            case 'like':
            case 'repost':
            case 'reply':
            case 'postReady':
            case 'scheduledPostLive':
            case 'uploadPartlyFailed':
                return ClientConfig.siteURL() + '/users/' + ClientConfig.get('currentUserUsername') + '/' + this.postId;
            case 'friendRequest':
                return ClientConfig.siteURL() + '/users/' + ClientConfig.get('currentUserUsername') + '/friends';
            case 'friendAccepted':
            case 'follow':
                return ClientConfig.siteURL() + '/users/' + this.actor.slug + '/';
            case 'message':
                return ClientConfig.siteURL() + '/messages/' + this.actor.slug;
            case 'mention':
                return ClientConfig.siteURL() + '/users/' + this.actor.slug + '/' + this.postId;
            case 'passwordRemovedGoogle':
                return ClientConfig.siteURL() + '/forgot-password';
            default:
                return null;
        }
    }

    toElement() {
        const div = document.createElement('article');
        div.className = 'Notification MountIn';
        div.dataset.notificationId = this.notificationId;

        const target = this.targetURL();
        const container = document.createElement(target === null ? 'div' : 'a');
        container.className = target === null ? 'NotificationContainer' : 'NotificationLink';
        if (target !== null) {
            container.href = target;
        }

        container.appendWithSpace(Avatar.forUser(this.actor));

        const info = document.createElement('div');

        const text = document.createElement('div');
        text.textContent = this.text();
        info.appendWithSpace(text);

        const created_at = parse_server_date(this.createdAt);

        const meta = document.createElement('time');
        meta.className = 'NotificationMeta RelativeTime';
        meta.dateTime = created_at.toISOString();
        meta.textContent = RelativeTime.format(this.createdAt);
        info.appendWithSpace(meta);

        container.appendWithSpace(info);
        div.appendWithSpace(container);

        this.element = div;

        return div;
    }
}
